import { DriverRegistry, WebMcpRegistrationLifecycle } from '@surfacerelay/browser-runtime';

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

console.log('SurfaceRelay browser-runtime clean-consumer smoke: PASS');
