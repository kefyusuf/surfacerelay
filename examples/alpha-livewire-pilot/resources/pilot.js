import { DriverRegistry, GlobalLivewireBrowserRuntime, LivewireBrowserDriver,
  WebMcpRegistrationLifecycle, resolveDocumentModelContext } from '@surfacerelay/browser-runtime';
const registry = new DriverRegistry();
registry.register('livewire', new LivewireBrowserDriver(new GlobalLivewireBrowserRuntime()));
const native = resolveDocumentModelContext(document);
let lease = null, signature = '', started = false, serial = Promise.resolve();
const element = (id) => document.getElementById(id);
function candidate() {
  const node = document.querySelector('[data-surfacerelay]');
  return node ? JSON.parse(node.textContent) : null;
}
async function reconcile() {
  const next = candidate(), nextSignature = JSON.stringify(next);
  if (nextSignature === signature) return;
  lease?.dispose(); lease = null; signature = '';
  if (!native) { element('native-state').textContent = 'Native WebMCP unavailable; human Livewire calls remain usable.'; signature = nextSignature; return; }
  if (!next) { element('native-state').textContent = 'Native WebMCP available; no authorized tool.'; signature = nextSignature; return; }
  const currentLease = await new WebMcpRegistrationLifecycle(native, registry).register([next]);
  if (JSON.stringify(candidate()) !== nextSignature) { currentLease.dispose(); schedule(); return; }
  lease = currentLease; signature = nextSignature;
  element('native-state').textContent = 'Native tool: pilot.livewire.orders.hold.v1 (exact Livewire component).';
}
function schedule() {
  serial = serial.catch(() => {}).then(reconcile).catch(() => { element('native-state').textContent = 'Native registration failed; human calls remain available.'; });
}
function setup() {
  if (started || !window.Livewire) return; started = true;
  window.Livewire.hook('component.init', ({cleanup}) => cleanup(schedule));
  window.Livewire.hook('morphed', schedule); schedule();
}
document.addEventListener('livewire:init', setup);
if (window.Livewire) setup();
window.addEventListener('pagehide', () => lease?.dispose());
async function api(path, payload) {
  const response = await fetch(path, {method: payload ? 'POST' : 'GET', credentials: 'same-origin',
    headers: {'Accept': 'application/json', 'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content},
    ...(payload ? {body: JSON.stringify(payload)} : {})});
  const body = await response.json(); if (!response.ok) throw new Error(`HTTP ${response.status}: request rejected`);
  return body;
}
for (const name of ['login', 'logout']) element(name).addEventListener('click', async () => {
  try { await api('/' + name, name === 'login' ? {email: element('actor').value + '@example.test', password: element('password').value} : {}); location.reload(); }
  catch (error) { element('session-state').textContent = error.message; }
});
element('remount').addEventListener('click', () => location.reload());
element('switch-tenant').addEventListener('click', async () => {
  try { await api('/tenant', {tenantId: element('tenant').value}); location.reload(); }
  catch (error) { element('session-state').textContent = error.message; }
});
element('refresh-evidence').addEventListener('click', async () => {
  try { element('effects').textContent = JSON.stringify((await api('/evidence')).effects, null, 2); }
  catch (error) { element('effects').textContent = error.message; }
});
