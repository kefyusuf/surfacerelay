import { DriverRegistry, WebMcpRegistrationLifecycle, resolveDocumentModelContext } from '@surfacerelay/browser-runtime';

const element = (id) => document.getElementById(id);
let csrf = '', session = {}, candidate = null, checkoutCandidate = null, lease = null, pending = null, busy = false;
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
  lease?.dispose(); lease = null; candidate = null; checkoutCandidate = null;
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
    try { checkoutCandidate = await api('/checkout/surface'); } catch { /* Payment discovery stays explicit. */ }
  } else list.textContent = 'Sign in to list tenant-scoped orders.';
  if (!nativeContext) element('native').textContent = 'Native WebMCP unavailable. Human HTTP tests remain available.';
  else if (!candidate && !checkoutCandidate) element('native').textContent = 'Native WebMCP available; no authorized active tool in this session.';
  else {
    try {
      lease = await new WebMcpRegistrationLifecycle(nativeContext, registry).register([candidate, checkoutCandidate].filter(Boolean));
      element('native').textContent = 'Native WebMCP tools registered for authorized refund / simulated checkout. Invocation still requires server authorization and human verification.';
    } catch { element('native').textContent = 'Native WebMCP registration failed. Human HTTP tests remain available.'; }
  }
  await showEvidence();
  await checkoutStatus();
}
async function checkoutStatus() {
  const link = element('checkout-3d-link'); link.hidden = true;
  if (!session.actorId) { element('checkout-state').textContent = 'Sign in to start checkout.'; return; }
  try {
    const state = await api('/checkout/status'); element('checkout-state').textContent = safe(state);
    if (state.nextPageUrl && /^\/3d\/[a-f0-9]{32}$/.test(state.nextPageUrl)) {
      link.href = state.nextPageUrl; link.hidden = false;
    }
  } catch (error) { element('checkout-state').textContent = error.message; }
}
registry.register('pilot.checkout.http', {execute: async (binding, input, context) => {
  if (!binding || typeof binding.bindingId !== 'string' || binding.driver !== 'pilot.checkout.http'
      || binding.target.endpoint !== '/checkout/invoke') throw new Error('Unsupported checkout binding.');
  const result = await api('/checkout/invoke', {bindingId: binding.bindingId, input}, context.signal);
  element('checkout-result').textContent = safe(result);
  await checkoutStatus();
  return result;
} });
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
const checkoutBinding = () => checkoutCandidate?.binding ?? {bindingId: session.bindingId,
  driver: 'pilot.checkout.http', target: {endpoint: '/checkout/invoke'}};
click('checkout-new', async () => {
  await api('/checkout/reset', {});
  await registry.requireDriver('pilot.checkout.http').execute(checkoutBinding(), {method: 'test-card'}, {});
});
click('checkout-retry', async () => {
  await registry.requireDriver('pilot.checkout.http').execute(checkoutBinding(), {method: 'test-card'}, {});
});
click('checkout-refresh', checkoutStatus);
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
