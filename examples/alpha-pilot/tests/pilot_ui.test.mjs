// DOM wiring tests using the real published package and a deterministic HTTP stub.
// These do not qualify native WebMCP/browser support.
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { randomUUID } from 'node:crypto';
import vm from 'node:vm';
import test from 'node:test';
import { DriverRegistry, WebMcpRegistrationLifecycle, resolveDocumentModelContext } from '@surfacerelay/browser-runtime';

const code = (await readFile(new URL('../resources/pilot.js', import.meta.url), 'utf8')).replace(/^import .*?;\n/, '');

async function harness() {
  const nodes = new Map(), tools = [], requests = [], reviews = new Map();
  const node = (id) => {
    if (!nodes.has(id)) nodes.set(id, { value: '', textContent: '', hidden: false, listeners: {},
      addEventListener(event, handler) { this.listeners[event] = handler; },
      replaceChildren() {}, append() {} });
    return nodes.get(id);
  };
  node('actor').value = 'owner'; node('password').value = 'acceptance-password'; node('reason').value = 'customer-request';
  let bindingId = 'binding-original', selection = [101], challenge = 0, reviewFailure = false;
  const document = { getElementById: node, createElement: () => ({append() {}}),
    createTextNode: (text) => text, querySelectorAll: () => [],
    modelContext: {registerTool(tool) { tools.push(tool); }} };
  const fetch = async (path, options) => {
    const payload = options.body ? JSON.parse(options.body) : undefined;
    requests.push({path, payload});
    let body, status = 200;
    if (path === '/session') body = {csrfToken: 'csrf', actorId: 1, tenantId: 'tenant-a', recordId: selection[0], selection, bindingId};
    else if (path === '/orders') body = {orders: [{id: 101, tenant_id: 'tenant-a'}, {id: 102, tenant_id: 'tenant-a'}]};
    else if (path === '/surface') body = {definition: {id: 'acceptance.orders.refund', version: 1,
      title: 'Refund', description: 'Pilot refund', inputSchema: {type: 'object'}, scope: 'page_scoped',
      effect: 'external_side_effect', risk: 'consequential', idempotency: 'required_key',
      outputSensitivity: 'sensitive', outputContentTrust: 'trusted_application_data', contextRequirements: []},
      binding: {bindingId, action: {id: 'acceptance.orders.refund', version: 1}, driver: 'acceptance.http',
        lifecycle: 'session', target: {endpoint: '/invoke'}}};
    else if (path === '/evidence') body = {effects: [], audits: []};
    else if (path === '/login') { bindingId = 'binding-replacement'; body = {csrfToken: 'csrf'}; }
    else if (path === '/approve') body = {receipt: 'opaque-receipt-secret'};
    else if (path === '/review') {
      if (reviewFailure) { status = 403; body = {error: 'request_rejected'}; }
      else body = reviews.get(payload.challengeId);
    }
    else if (path === '/invoke') {
      if (payload.bindingId !== bindingId) { status = 409; body = {error: 'request_rejected'}; }
      else if (payload.receipt) body = {status: 'rejected', error: {code: 'confirmation_receipt_invalid'}};
      else {
        const challengeId = `challenge-${++challenge}`;
        reviews.set(challengeId, {tenantId: 'tenant-a', orderIds: [...selection], reason: payload.input.reason});
        body = {status: 'confirmation_required', confirmation: {challengeId}};
      }
    } else throw new Error(`Unexpected request: ${path}`);
    return {ok: status === 200, status, json: async () => body};
  };
  vm.runInNewContext(code, {document, fetch, DriverRegistry, WebMcpRegistrationLifecycle,
    resolveDocumentModelContext, crypto: {randomUUID}, window: {addEventListener() {}}, console});
  const settle = async () => { for (let i = 0; i < 10; i++) await new Promise(resolve => setImmediate(resolve)); };
  await settle();
  assert.match(node('boot').textContent, /Published browser runtime loaded/);
  return {node, tools, requests, click: async (id) => { await node(id).listeners.click(); await settle(); },
    select(ids) { selection = ids; }, failReview() { reviewFailure = true; }, settle};
}

test('captured native handle cannot silently retarget a replacement binding', async () => {
  const app = await harness();
  const original = app.tools[0];
  await app.click('login');
  assert.equal(app.tools.length, 2);
  await assert.rejects(original.execute({reason: 'customer-request'}, {signal: new AbortController().signal}), /409/);
  assert.equal(app.requests.filter(row => row.path === '/invoke').at(-1).payload.bindingId, 'binding-original');
});

test('new challenge replaces the human review with its server-issued context', async () => {
  const app = await harness();
  const tool = app.tools[0], options = {signal: new AbortController().signal};
  await tool.execute({reason: 'customer-request'}, options);
  assert.match(app.node('confirmation-summary').textContent, /101/);
  app.select([102]);
  await tool.execute({reason: 'customer-request'}, options);
  assert.match(app.node('confirmation-summary').textContent, /102/);
  assert.doesNotMatch(app.node('confirmation-summary').textContent, /101/);
});

test('changed reason sends the old receipt for server rejection without silently approving', async () => {
  const app = await harness();
  await app.click('request');
  const original = app.requests.filter(row => row.path === '/invoke').at(-1).payload;
  await app.click('approve');
  app.node('reason').value = 'duplicate-order';
  await app.click('execute');
  const attempt = app.requests.filter(row => row.path === '/invoke').at(-1).payload;
  assert.equal(attempt.input.reason, 'duplicate-order');
  assert.equal(attempt.idempotencyKey, original.idempotencyKey);
  assert.equal(attempt.receipt, 'opaque-receipt-secret');
  assert.equal(app.requests.filter(row => row.path === '/approve').length, 1);
  assert.match(app.node('result').textContent, /rejected/);
  for (const id of ['result', 'confirmation-summary', 'effects']) assert.doesNotMatch(app.node(id).textContent, /opaque-receipt-secret/);
});

test('failed new-challenge review disables approval instead of retaining an old summary', async () => {
  const app = await harness();
  const tool = app.tools[0], options = {signal: new AbortController().signal};
  await tool.execute({reason: 'customer-request'}, options);
  app.select([102]); app.failReview();
  await assert.rejects(tool.execute({reason: 'customer-request'}, options), /403/);
  assert.equal(app.node('confirmation').hidden, true);
  await app.click('approve');
  assert.equal(app.requests.filter(row => row.path === '/approve').length, 0);
});
