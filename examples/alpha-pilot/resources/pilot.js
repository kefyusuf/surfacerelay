import { DriverRegistry, WebMcpRegistrationLifecycle, resolveDocumentModelContext } from '@surfacerelay/browser-runtime';

const element = (id) => document.getElementById(id);
let csrf = '', session = {}, candidate = null, lease = null, pending = null, busy = false;
const nativeContext = resolveDocumentModelContext(document);
const registry = new DriverRegistry();
const currentBinding = () => candidate?.binding ?? {bindingId: session.bindingId,
  driver: 'acceptance.http', target: {endpoint: '/invoke'}};

function safe(value) {
  return JSON.stringify(value, (key, item) => key === 'receipt' || key === 'csrfToken' ? '[hidden]' : item, 2);
}
async function api(path, payload, signal) {
  const response = await fetch(path, { method: payload === undefined ? 'GET' : 'POST',
    headers: { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    credentials: 'same-origin', signal, ...(payload === undefined ? {} : { body: JSON.stringify(payload) }) });
  const body = await response.json();
  if (body.csrfToken) csrf = body.csrfToken;
  if (!response.ok) throw new Error(`HTTP ${response.status}: ${body.error ?? 'request rejected'}`);
  return body;
}
async function showEvidence() {
  if (!session.actorId) { element('effects').textContent = '[]'; return; }
  const evidence = await api('/evidence');
  element('effects').textContent = safe(evidence.effects);
}
function showResult(result) {
  element('result').textContent = safe(result);
  element('confirmation').hidden = !pending?.challenge;
  if (pending?.challenge) element('confirmation-summary').textContent =
    `Pending refund: ${pending.orders.join(', ')} in ${pending.tenant}, reason ${pending.input.reason}. Review before approval.`;
}
async function invoke(input, options = {}, binding = currentBinding()) {
  if (busy) throw new Error('A request is already running.');
  busy = true;
  try {
    if (!pending) {
      pending = { key: `pilot-${crypto.randomUUID()}`, input, binding };
    }
    const result = await api('/invoke', { bindingId: binding.bindingId,
      input, idempotencyKey: pending.key, ...(pending.receipt ? { receipt: pending.receipt } : {}) }, options.signal);
    if (result.status === 'confirmation_required') {
      pending.challenge = null; pending.receipt = null;
      element('confirmation').hidden = true;
      pending.input = input; pending.binding = binding;
      const challengeId = result.confirmation.challengeId;
      const review = await api('/review', {challengeId}, options.signal);
      pending.challenge = challengeId;
      pending.orders = review.orderIds; pending.tenant = review.tenantId;
    }
    if (result.status === 'succeeded') { pending.challenge = null; pending.receipt = null; }
    showResult(result);
    await showEvidence();
    return result;
  } finally { busy = false; }
}
registry.register('acceptance.http', { execute: (binding, input, context) => {
  if (!binding || typeof binding.bindingId !== 'string' || binding.driver !== 'acceptance.http'
      || binding.target.endpoint !== '/invoke') {
    throw new Error('Unsupported pilot binding target.');
  }
  return invoke(input, context, binding);
} });

async function refresh() {
  lease?.dispose(); lease = null; candidate = null;
  session = await api('/session');
  const { csrfToken: _csrf, bindingId: _binding, ...visible } = session;
  element('session').textContent = safe(visible);
  element('tenant').value = session.tenantId ?? 'tenant-a';
  const list = element('orders'); list.replaceChildren();
  if (session.actorId) {
    for (const order of (await api('/orders')).orders) {
      const label = document.createElement('label'), checkbox = document.createElement('input');
      checkbox.type = 'checkbox'; checkbox.value = String(order.id); checkbox.checked = session.selection.includes(order.id);
      label.append(checkbox, document.createTextNode(` Order ${order.id} · ${order.tenant_id}`)); list.append(label);
    }
    try { candidate = await api('/surface'); } catch { /* Discovery denial does not bypass invocation authorization. */ }
  } else list.textContent = 'Sign in to list tenant-scoped orders.';
  if (!nativeContext) element('native').textContent = 'Native WebMCP unavailable. Human HTTP tests remain available.';
  else if (!candidate) element('native').textContent = 'Native WebMCP available; no authorized active tool in this session.';
  else {
    try {
      lease = await new WebMcpRegistrationLifecycle(nativeContext, registry).register([candidate]);
      element('native').textContent = 'Native WebMCP tool registered: acceptance.orders.refund.v1. Agent invocation still requires server authorization and human approval.';
    } catch { element('native').textContent = 'Native WebMCP registration failed. Human HTTP tests remain available.'; }
  }
  await showEvidence();
}
function click(id, action) {
  element(id).addEventListener('click', async () => {
    const button = element(id); button.disabled = true;
    try { await action(); } catch (error) { element('result').textContent = error.message; }
    finally { button.disabled = false; }
  });
}
click('login', async () => { await api('/login', { email: `${element('actor').value}@example.test`, password: element('password').value }); pending = null; showResult({status: 'signed_in'}); await refresh(); });
click('logout', async () => { await api('/logout', {}); pending = null; showResult({status: 'signed_out'}); await refresh(); });
click('refresh', refresh);
click('switch-tenant', async () => { await api('/tenant', { tenantId: element('tenant').value }); await refresh(); });
click('selection', async () => {
  const ids = [...document.querySelectorAll('#orders input:checked')].map((node) => Number(node.value));
  if (!ids.length) throw new Error('Select at least one order.');
  await api('/context', { recordId: ids[0], selection: ids }); await refresh();
});
click('request', async () => { pending = null; await registry.requireDriver('acceptance.http').execute(currentBinding(), { reason: element('reason').value }, {}); });
click('approve', async () => {
  if (!pending?.challenge) throw new Error('Request a refund first.');
  pending.receipt = (await api('/approve', {challengeId: pending.challenge})).receipt;
  element('result').textContent = 'Human approval recorded. Receipt hidden; execute before it expires.';
});
click('execute', async () => { if (!pending?.receipt) throw new Error('Approve the pending request first.'); await invoke({reason: element('reason').value}, {}, pending.binding); });
click('replay', async () => { if (!pending) throw new Error('Request a refund first.'); pending.receipt = null; await invoke(pending.input); });
click('forged', async () => { showResult(await api('/invoke', {input: {reason: 'customer-request'}, idempotencyKey: `pilot-${crypto.randomUUID()}`, metadata: {actorId: 1, tenantId: 'tenant-b', confirmed: true, permissions: ['acceptance.refund']}})); await showEvidence(); });
click('foreign-order', async () => { showResult(await api('/context', {recordId: 201, selection: [201]})); });
for (const operation of ['expire', 'unsupported']) click(operation, async () => { await api('/binding', {operation}); await refresh(); element('result').textContent = 'Binding invalidated. Invocation must fail closed.'; });
window.addEventListener('pagehide', () => lease?.dispose());
refresh().then(() => { element('boot').textContent = 'Published browser runtime loaded; app-owned HTTP driver registered.'; }).catch((error) => { element('boot').textContent = `Startup failed: ${error.message}`; });
