#!/usr/bin/env node
/**
 * A2-08 native Chrome proof harness.
 *
 * Drives the OFFICIAL Chrome DevTools MCP (pinned 1.10.1) over stdio JSON-RPC,
 * launching an isolated Chrome 150+ process with the WebMCP feature enabled.
 * The proof surface is the agent surface itself: `list_webmcp_tools` for
 * discovery and `execute_webmcp_tool` for invocation, exactly as in the
 * accepted T-907/T-910 native pattern. This is local acceptance tooling, not
 * a SurfaceRelay dependency, and it implements no MCP protocol stack of the
 * project — it is a test client for a maintained external server.
 *
 * Modes:
 *   node run_native_proof.mjs probe    — dump MCP tool inventory + first page snapshot
 *   node run_native_proof.mjs proof    — full discovery/invocation/negative flow + receipts
 */

import { execSync, spawn } from 'node:child_process';
import { once } from 'node:events';
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const MODE = process.argv[2] ?? 'probe';
if (MODE !== 'probe' && MODE !== 'proof' && MODE !== 'diag' && MODE !== 'guest') {
  console.error('Usage: node run_native_proof.mjs [probe|proof|diag|guest]');
  process.exit(2);
}

const CHROME_EXE = process.env.A2_CHROME_PATH ?? 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe';
const BASE_URL = process.env.A2_BASE_URL ?? 'http://127.0.0.1:8080';
const MEMBER_EMAIL = process.env.A2_MEMBER_EMAIL ?? 'a2.member@example.test';
const MEMBER_PASSWORD = process.env.A2_MEMBER_PASSWORD ?? 'a2-acceptance-password';
const MCP_PACKAGE = 'chrome-devtools-mcp@1.10.1';
const HERE = dirname(fileURLToPath(import.meta.url));

function resolveNpxCli() {
  const candidate = join(dirname(process.execPath), 'node_modules', 'npm', 'bin', 'npx-cli.js');
  return candidate;
}

class McpClient {
  constructor(proc) {
    this.proc = proc;
    this.buffer = '';
    this.nextId = 1;
    this.pending = new Map();
    this.events = [];
    proc.stdout.setEncoding('utf8');
    proc.stdout.on('data', (chunk) => this.#onData(chunk));
    proc.stderr.setEncoding('utf8');
    proc.stderr.on('data', (chunk) => {
      if (process.env.A2_MCP_DEBUG) process.stderr.write(`[mcp-stderr] ${chunk}`);
    });
  }

  #onData(chunk) {
    this.buffer += chunk;
    let newlineIndex = this.buffer.indexOf('\n');
    while (newlineIndex !== -1) {
      const line = this.buffer.slice(0, newlineIndex).trim();
      this.buffer = this.buffer.slice(newlineIndex + 1);
      if (line.length > 0) this.#dispatch(line);
      newlineIndex = this.buffer.indexOf('\n');
    }
  }

  #dispatch(line) {
    let message;
    try {
      message = JSON.parse(line);
    } catch {
      return;
    }
    if (message.id === undefined) {
      this.events.push(message);
      return;
    }
    const pending = this.pending.get(message.id);
    if (pending === undefined) return;
    this.pending.delete(message.id);
    if (message.error) pending.reject(new Error(`MCP error ${message.error.code}: ${message.error.message}`));
    else pending.resolve(message.result);
  }

  notify(method, params = {}) {
    this.proc.stdin.write(`${JSON.stringify({ jsonrpc: '2.0', method, params })}\n`);
  }

  request(method, params = {}, timeoutMs = 120_000) {
    const id = this.nextId;
    this.nextId += 1;
    const promise = new Promise((resolve, reject) => {
      const timer = setTimeout(() => {
        this.pending.delete(id);
        reject(new Error(`MCP request timed out: ${method}`));
      }, timeoutMs);
      this.pending.set(id, {
        resolve: (value) => {
          clearTimeout(timer);
          resolve(value);
        },
        reject: (error) => {
          clearTimeout(timer);
          reject(error);
        },
      });
    });
    this.proc.stdin.write(`${JSON.stringify({ jsonrpc: '2.0', id, method, params })}\n`);
    return promise;
  }

  async close() {
    this.proc.stdin.end();
    // Kill the whole process tree (npx -> node -> Chrome) so the isolated
    // profile and any session inside it truly die between harness runs.
    try {
      execSync(`taskkill /PID ${this.proc.pid} /T /F`, { stdio: 'ignore' });
    } catch {
      this.proc.kill();
    }
    await once(this.proc, 'exit').catch(() => {});
  }
}

async function startMcp() {
  const args = [
    resolveNpxCli(),
    '--yes',
    MCP_PACKAGE,
    '--isolated',
    '--categoryExperimentalWebmcp=true',
    `--chromeArg=--enable-features=WebMCP`,
    `--executablePath=${CHROME_EXE}`,
  ];
  const proc = spawn(process.execPath, args, { stdio: ['pipe', 'pipe', 'pipe'] });
  const client = new McpClient(proc);
  const exitPromise = once(proc, 'exit').then(([code]) => ({ code }));
  const failTimer = setTimeout(() => {
    console.error('chrome-devtools-mcp did not initialize in 120s; killing.');
    proc.kill();
  }, 120_000);
  try {
    const result = await client.request('initialize', {
      protocolVersion: '2025-06-18',
      capabilities: {},
      clientInfo: { name: 'a2-08-native-proof', version: '0.1.0' },
    });
    client.notify('notifications/initialized');
    console.log('MCP server:', result?.serverInfo?.name, result?.serverInfo?.version);
  } finally {
    clearTimeout(failTimer);
  }
  void exitPromise;
  return client;
}

function textOf(toolResult) {
  const content = toolResult?.content;
  if (!Array.isArray(content)) return '';
  return content.map((part) => (typeof part?.text === 'string' ? part.text : '')).join('\n');
}

async function call(client, name, args = {}, timeoutMs = 120_000) {
  const result = await client.request('tools/call', { name, arguments: args }, timeoutMs);
  if (result?.isError) throw new Error(`Tool ${name} failed: ${textOf(result)}`);
  return result;
}

/** chrome-devtools-mcp 1.10.1 requires a numeric pageId on every page tool. */
async function primaryPageId(client) {
  const pages = textOf(await call(client, 'list_pages', {}));
  if (process.env.A2_MCP_DEBUG) console.log('list_pages raw:\n' + pages);
  const lines = pages.split('\n').filter((line) => /^\s*(?:\[selected\]\s*)?\d+\s*:/.test(line));
  if (lines.length === 0) throw new Error('list_pages produced no parsable page line:\n' + pages);
  const ids = lines
    .map((line) => Number(line.trim().replace(/^\[selected\]\s*/, '').match(/^(\d+)\s*:/)?.[1]))
    .filter((id) => Number.isFinite(id))
    .sort((a, b) => a - b);
  return ids[0];
}


/** evaluate_script wraps values in a markdown fence; unwrap and parse. */
function parseEval(text) {
  const match = text.match(/```json\s*([\s\S]*?)```/);
  if (!match) return text.trim();
  try { return JSON.parse(match[1]); } catch { return match[1].trim(); }
}

function findUid(snapshotText, matcher) {
  const lines = snapshotText.split('\n');
  for (const line of lines) {
    const uidMatch = line.match(/uid=([A-Za-z0-9_-]+)/);
    if (uidMatch && matcher(line)) return uidMatch[1];
  }
  return null;
}

async function loginAsMember(client, pageId) {
  await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin/login` });

  // Filament disables the form until Livewire has hydrated; wait for it.
  let hydrated = false;
  for (let attempt = 0; attempt < 40; attempt += 1) {
    const check = textOf(await call(client, 'evaluate_script', {
      pageId,
      function: '() => Boolean(window.Livewire && Array.isArray(window.Livewire.all()) && window.Livewire.all().length >= 1)',
    }));
    if (check.includes('true')) {
      hydrated = true;
      break;
    }
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  if (!hydrated) throw new Error('Livewire did not hydrate the login page within 20s');

  let snapshot = textOf(await call(client, 'take_snapshot', { pageId }));
  const emailUid = findUid(snapshot, (line) => /email/i.test(line) && /(textbox|input)/i.test(line));
  if (emailUid === null) throw new Error('Login email field not found in snapshot');
  const passwordUid = findUid(snapshot, (line) => /password/i.test(line) && /(textbox|input)/i.test(line));
  if (passwordUid === null) throw new Error('Login password field not found in snapshot');
  await call(client, 'fill', { pageId, uid: emailUid, value: MEMBER_EMAIL });
  await call(client, 'fill', { pageId, uid: passwordUid, value: MEMBER_PASSWORD });
  const submitUid = findUid(snapshot, (line) => /(sign in|log in|giri)/i.test(line) && /button/i.test(line));
  if (submitUid === null) throw new Error('Login submit button not found in snapshot');
  await call(client, 'click', { pageId, uid: submitUid });

  let path = '';
  for (let attempt = 0; attempt < 40; attempt += 1) {
    path = textOf(await call(client, 'evaluate_script', { pageId, function: '() => location.pathname' }));
    if (!path.includes('/admin/login')) break;
    await new Promise((resolve) => setTimeout(resolve, 500));
  }
  if (path.includes('/admin/login')) {
    const consoleDump = textOf(await call(client, 'list_console_messages', { pageId }));
    const networkDump = textOf(await call(client, 'list_network_requests', {
      pageId,
      pageSize: 200,
      resourceTypes: ['xhr', 'fetch', 'document'],
    }));
    writeFileSync(join(HERE, 'login-failure-network.txt'), networkDump, 'utf8');
    throw new Error('Login did not leave the login page; console:\n' + consoleDump.slice(0, 600)
      + '\n=== network (login-failure-network.txt) ===\n' + networkDump.slice(0, 3000));
  }
}

async function main() {
  const client = await startMcp();
  try {
    const inventory = await client.request('tools/list', {});
    const toolNames = (inventory?.tools ?? []).map((tool) => tool.name).sort();
    console.log('MCP tools:', toolNames.join(', '));
    const required = ['navigate_page', 'take_snapshot', 'click', 'fill', 'wait_for', 'list_webmcp_tools', 'execute_webmcp_tool'];
    const missing = required.filter((name) => !toolNames.includes(name));
    if (missing.length > 0) throw new Error(`Missing required MCP tools: ${missing.join(', ')}`);

    const pageId = await primaryPageId(client);
    console.log('primary pageId:', pageId);

    if (MODE === 'probe') {
      const webmcp = (inventory?.tools ?? []).filter((tool) => /webmcp|evaluate_script|wait_for|fill_form/i.test(tool.name));
      writeFileSync(join(HERE, 'tool-schemas.json'), JSON.stringify(inventory?.tools ?? [], null, 2), 'utf8');
      for (const tool of webmcp) {
        console.log(`--- ${tool.name} ---`);
        console.log(JSON.stringify(tool.inputSchema?.properties ?? {}, null, 2).slice(0, 900));
      }
      await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin/login` });
      for (const fn of [
        '() => typeof window.Livewire',
        '() => typeof window.Livewire?.first',
        '() => typeof document.modelContext',
        '() => Boolean(window.__surfacerelayA2Registration)',
      ]) {
        const value = textOf(await call(client, 'evaluate_script', { pageId, function: fn }));
        console.log(`eval ${fn} => ${value.trim().slice(-120)}`);
      }
      const consoleDump = textOf(await call(client, 'list_console_messages', { pageId }));
      console.log('=== console ===');
      console.log(consoleDump.slice(0, 2000));
      return;
    }

    if (MODE === 'guest') {
      // Discovery is not authorization: an unauthenticated visitor must not
      // see the tool even when the URL is known.
      await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin/a2-orders` });
      await new Promise((resolve) => setTimeout(resolve, 3000));
      const pathNow = String(parseEval(textOf(await call(client, 'evaluate_script', {
        pageId,
        function: '() => location.pathname',
      }))));
      const guestTools = textOf(await call(client, 'list_webmcp_tools', { pageId }));
      if (!pathNow.includes('/admin/login')) {
        throw new Error(`Guest run appears authenticated (landed on ${pathNow}) — harness lifecycle leak, not an app result. Kill lingering Chrome trees and rerun.`);
      }
      const guestPass = !guestTools.includes('a2.orders.inspect.v1');
      writeFileSync(join(HERE, 'guest-result.json'), JSON.stringify({
        gate: 'A2-08-native-chrome-guest-discovery',
        result: guestPass ? 'PASS_A2_08_GUEST_NO_DISCOVERY' : 'FAIL',
        landedOn: pathNow,
        tools: guestTools.slice(0, 200),
      }, null, 2) + '\n', 'utf8');
      console.log(`guest: landed=${pathNow} tools=${guestTools.slice(0, 80)}`);
      console.log(guestPass ? 'A2-08 guest negative: PASS_A2_08_GUEST_NO_DISCOVERY' : 'A2-08 guest negative: FAIL');
      if (!guestPass) process.exitCode = 1;
      return;
    }

    await loginAsMember(client, pageId);
    console.log('login: PASS');

    if (MODE === 'diag') {
      const diagUrl = process.env.A2_DIAG_URL ?? `${BASE_URL}/admin/a2-orders`;
      await call(client, 'navigate_page', { pageId, url: diagUrl });
      await new Promise((resolve) => setTimeout(resolve, 3000));
      for (const fn of [
        '() => location.pathname',
        '() => Boolean(document.querySelector("script[type=\\"importmap\\"]"))',
        '() => document.querySelector("script[type=\\"importmap\\"]")?.textContent?.slice(0, 160) ?? "none"',
        '() => Array.from(document.querySelectorAll("script[type=\\"module\\"]")).map(s => s.src.split("/").pop())',
        'async () => String((await fetch("/js/a2-surfacerelay-page.mjs")).status)',
        'async () => String((await fetch("/js/surfacerelay-browser-runtime.js")).status)',
        '() => typeof document.modelContext',
        '() => typeof document.modelContext?.registerTool',
        '() => Array.isArray(window.Livewire.all()) ? window.Livewire.all().length : "n/a"',
        '() => window.Livewire.all().map(c => Boolean(c.$wire && c.$wire.get && c.$wire.get("surfacerelayDescriptor")))',
        'async () => { try { await import("/js/surfacerelay-browser-runtime.js"); return "runtime_imported"; } catch (e) { return "RUNTIME_ERROR: " + (e?.message ?? String(e)); } }',
        'async () => { try { await import("/js/a2-surfacerelay-glue.mjs"); return "glue_imported"; } catch (e) { return "GLUE_ERROR: " + (e?.message ?? String(e)); } }',
        'async () => { try { await import("/js/a2-surfacerelay-page.mjs"); return "module_imported"; } catch (e) { return "IMPORT_ERROR: " + (e?.message ?? String(e)); } }',
        '() => JSON.stringify(window.__surfacerelayA2Registration ?? null)',
      ]) {
        const value = textOf(await call(client, 'evaluate_script', { pageId, function: fn }));
        console.log(`eval ${fn}\n  => ${value.trim().slice(-400)}`);
      }
      const consoleDump = textOf(await call(client, 'list_console_messages', { pageId }));
      console.log('=== console ===');
      console.log(consoleDump.slice(0, 1500));
      return;
    }

    await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin/a2-orders` });
    await new Promise((resolve) => setTimeout(resolve, 2500));
    const discovery = textOf(await call(client, 'list_webmcp_tools', { pageId }));
    console.log('=== list_webmcp_tools ===');
    console.log(discovery);
    if (!discovery.includes('a2.orders.inspect.v1')) {
      throw new Error('Discovery did not expose a2.orders.inspect.v1');
    }

    const auditCount = () => Number(execSync(
      'docker exec a2-08-serve php /consumer/instance/a2-08-audit-count.php',
    ).toString().trim());

    const results = { discovery: 'PASS', invocation: null, negatives: {} };

    const beforeAudit = auditCount();
    const invocation = textOf(await call(client, 'execute_webmcp_tool', {
      pageId,
      toolName: 'a2.orders.inspect.v1',
      input: JSON.stringify({ order_id: 101 }),
    }, 120_000));
    console.log('=== execute (order 101) ===');
    console.log(invocation);
    if (!/surfacerelay\.webmcp\.execution\.v1/.test(invocation) || !/"status"\s*:\s*"returned"/.test(invocation)) {
      throw new Error('Invocation did not return the expected execution envelope');
    }
    const afterAudit = auditCount();
    results.invocation = 'PASS';
    results.auditRowsBefore = beforeAudit;
    results.auditRowsAfter = afterAudit;
    if (afterAudit <= beforeAudit) {
      throw new Error(`Invocation did not create an audit row (${beforeAudit} -> ${afterAudit})`);
    }
    console.log(`audit rows: ${beforeAudit} -> ${afterAudit} (same server operation)`);

    const expectFailure = async (label, input) => {
      const outcome = textOf(await call(client, 'execute_webmcp_tool', {
        pageId,
        toolName: 'a2.orders.inspect.v1',
        input: JSON.stringify(input),
      }, 120_000));
      const pass = /"status"\s*:\s*"execution_failed"/.test(outcome) && /unknown/.test(outcome);
      results.negatives[label] = pass ? 'PASS' : 'FAIL';
      console.log(`negative ${label}: ${pass ? 'PASS' : 'FAIL'} -> ${outcome.slice(0, 220)}`);
      return pass;
    };

    const forged = await expectFailure('forged_tenant_field', { order_id: 101, forged_tenant: 'tenant-b' });

    // The void server method resolves normally even when the server DENIES the
    // dispatch: the envelope truthfully reports `returned` (returned is not
    // business success), and the denial lives in server-owned page state.
    const inspectionStatus = async () => {
      const value = textOf(await call(client, 'evaluate_script', {
        pageId,
        function: '() => { const c = window.Livewire.all().find(c => c.$wire.get("surfacerelayDescriptor")); return c ? c.$wire.get("inspectionStatus") : "no-descriptor-component"; }',
      }));
      return String(parseEval(value));
    };
    await call(client, 'execute_webmcp_tool', {
      pageId,
      toolName: 'a2.orders.inspect.v1',
      input: JSON.stringify({ order_id: 201 }),
    }, 120_000);
    const foreignStatus = await inspectionStatus();
    const foreignDenied = foreignStatus === 'denied';
    results.negatives.foreign_tenant_order_denied_in_state = foreignDenied ? 'PASS' : `FAIL(status=${foreignStatus})`;
    console.log(`negative foreign_tenant_order_denied_in_state: ${foreignDenied ? 'PASS' : 'FAIL'} (server state=${foreignStatus}; envelope stays 'returned' for a void method)`);

    const missingInput = await expectFailure('missing_required_input', {});
    if (!forged || !foreignDenied || !missingInput) {
      throw new Error('One or more invocation negatives failed: ' + JSON.stringify(results.negatives));
    }

    // Unmount/lifecycle negative: navigating to a page without the descriptor
    // must dispose the lease; the tool must disappear from discovery.
    await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin` });
    await new Promise((resolve) => setTimeout(resolve, 2500));
    const afterNavigate = textOf(await call(client, 'list_webmcp_tools', { pageId }));
    results.negatives.unmount_dispose = afterNavigate.includes('a2.orders.inspect.v1') ? 'FAIL' : 'PASS';
    console.log('negative unmount_dispose: ' + results.negatives.unmount_dispose
      + ' -> ' + afterNavigate.slice(0, 160));

    // Re-registration on the descriptor-carrying page (implemented behavior).
    await call(client, 'navigate_page', { pageId, url: `${BASE_URL}/admin/a2-orders` });
    await new Promise((resolve) => setTimeout(resolve, 2500));
    const reRegistered = textOf(await call(client, 'list_webmcp_tools', { pageId }));
    results.reregistration = reRegistered.includes('a2.orders.inspect.v1') ? 'PASS' : 'FAIL';
    console.log('reregistration: ' + results.reregistration);

    mkdirSync(HERE, { recursive: true });
    writeFileSync(join(HERE, 'discovery-dump.txt'), discovery, 'utf8');
    writeFileSync(join(HERE, 'invocation-dump.txt'), invocation, 'utf8');
    writeFileSync(join(HERE, 'proof-result.json'), JSON.stringify({
      gate: 'A2-08-native-chrome-discovery-invocation',
      result: 'PASS_A2_08_NATIVE_DISCOVERY_INVOCATION_ONLY',
      chrome: CHROME_EXE,
      baseUrl: BASE_URL,
      ...results,
      scope: 'Real Chrome document.modelContext discovery and invocation of the A2-02 read-only consumer tool. No write path, no release claim.',
    }, null, 2) + '\n', 'utf8');
    console.log('A2-08 proof: PASS_A2_08_NATIVE_DISCOVERY_INVOCATION_ONLY');
  } finally {
    await client.close();
  }
}

main().catch((error) => {
  console.error('A2-08 harness error:', error.message);
  process.exitCode = 1;
});
