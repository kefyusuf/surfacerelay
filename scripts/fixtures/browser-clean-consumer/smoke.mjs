import { DriverRegistry, WebMcpRegistrationLifecycle } from '@surfacerelay/browser-runtime';
import {
  FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime,
} from '@surfacerelay/browser-runtime';

const registry = new DriverRegistry();
const driver = {
  async execute() {
    return undefined;
  },
};

registry.register('fixture', driver);

if (registry.requireDriver('fixture') !== driver) {
  throw new Error('DriverRegistry did not resolve the exact registered driver.');
}

let rejected = false;
try {
  registry.requireDriver('unsupported');
} catch (error) {
  rejected = error instanceof Error && error.message === 'Unsupported binding driver.';
}

if (!rejected) {
  throw new Error('DriverRegistry did not fail closed for an unsupported driver.');
}

let registered;
const lifecycle = new WebMcpRegistrationLifecycle({
  async registerTool(tool) { registered = tool; },
}, registry, { resultMode: 'envelope' });
const lease = await lifecycle.register([{
  definition: {
    id: 'fixture.read', version: 1, title: 'Fixture', description: 'Fixture read.',
    inputSchema: { type: 'object' }, scope: 'portable', effect: 'read', risk: 'low',
    idempotency: 'none', outputSensitivity: 'normal', outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
  },
  binding: {
    bindingId: 'fixture-binding', action: { id: 'fixture.read', version: 1 },
    driver: 'fixture', lifecycle: 'page', target: {}, expiresAt: null,
  },
}]);
try {
  const result = await registered.execute({}, { signal: new AbortController().signal });
  if (JSON.stringify(result) !== JSON.stringify({
    kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'undefined' },
  })) throw new Error('Installed execution envelope did not preserve undefined.');
} finally { lease.dispose(); }

// An installed opt-in driver must reject an unexposed binding before touching
// framework or selection state. This runs without a DOM or Livewire globals.
let frameworkReads = 0;
let selectionWrites = 0;
const filament = new FilamentBrowserDriver({
  find() { frameworkReads++; throw new Error('Unexpected framework lookup'); },
}, {
  tools: [], coordinator: new FilamentSelectionCoordinator(),
  selectionRuntime: { sync() { selectionWrites++; } },
});
if (typeof new GlobalFilamentSelectionRuntime().sync !== 'function') {
  throw new Error('Installed default selection runtime is unavailable.');
}
let unexposedRejected = false;
try {
  await filament.execute({
    bindingId: 'unexposed', action: { id: 'fixture.read', version: 1 },
    driver: 'livewire', lifecycle: 'component', expiresAt: null,
    target: { componentId: 'fixture', method: 'read', inputOrder: [], requiredCount: 0 },
  }, {}, {});
} catch { unexposedRejected = true; }
if (!unexposedRejected || frameworkReads !== 0 || selectionWrites !== 0) {
  throw new Error('Installed Filament driver did not reject unexposed bindings before side effects.');
}

console.log('SurfaceRelay browser-runtime clean-consumer smoke: PASS');
