/**
 * T-913 Node acceptance test for the quickstart browser glue.
 *
 * Runs the REAL published `@surfacerelay/browser-runtime@0.1.0-alpha.3`
 * package against a stubbed `document.modelContext` and a stubbed Livewire
 * global, importing the exact module the document ships
 * (`public/js/quickstart-register.mjs`). This proves registration semantics
 * and envelope return behavior without a browser; real Chrome interop is the
 * separate A2-08 native proof.
 */

import test from 'node:test';
import assert from 'node:assert/strict';

const registered = [];
const signals = [];

const descriptorFixture = {
  definition: {
    id: 'quickstart.orders.inspect',
    version: 1,
    title: 'Inspect order',
    description: 'Read the status of one order belonging to the tenant.',
    inputSchema: {
      type: 'object',
      properties: { order_id: { type: 'integer', minimum: 1 } },
      required: ['order_id'],
      additionalProperties: false,
    },
    outputSchema: null,
    scope: 'page_scoped',
    effect: 'read',
    risk: 'low',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: ['authenticated_actor', 'tenant'],
  },
  binding: {
    bindingId: 'sr_000102030405060708090a0b0c0d0e0f',
    action: { id: 'quickstart.orders.inspect', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: { componentId: 'stub-component-01', method: 'inspectOrder', inputOrder: ['order_id'], requiredCount: 1 },
    expiresAt: null,
  },
};

function stubWire() {
  const wire = {
    $id: 'stub-component-01',
    async $call(method, ...params) {
      calls.push({ method, params });
      return undefined;
    },
    intercept(method, callback) {
      callback({ action: { cancel() {} }, onSend() {} });
      return () => {};
    },
  };
  return wire;
}

const calls = [];
const harnessWire = stubWire();

process.title = 'quickstart-glue-test';
globalThis.window = globalThis;
// The module registers Livewire navigation listeners on window; provide the
// browser surface the harness stub needs.
const harnessListeners = [];
globalThis.addEventListener = (type, fn) => harnessListeners.push({ type, fn });
globalThis.document = {
  readyState: 'complete',
  addEventListener() {},
  modelContext: {
    async registerTool(tool, options) {
      registered.push(tool);
      signals.push(options.signal);
    },
  },
};
globalThis.Livewire = {
  all: () => [{ $wire: { $id: 'stub-component-01', get: (prop) => (prop === 'surfacerelayDescriptor' ? descriptorFixture : undefined) } }],
  find: (componentId) => (componentId === harnessWire.$id ? harnessWire : undefined),
};

function pollFor(predicate, timeoutMs = 5000) {
  return new Promise((resolve, reject) => {
    const started = Date.now();
    const tick = () => {
      if (predicate()) return resolve();
      if (Date.now() - started > timeoutMs) return reject(new Error('condition not met in time'));
      setTimeout(tick, 25);
    };
    tick();
  });
}

await import('../public/js/quickstart-register.mjs');

test('test_glue_registers_exactly_one_tool_with_published_projection_contract', async () => {
  await pollFor(() => globalThis.__quickstartRegistration?.registered === true);

  assert.equal(registered.length, 1);
  const tool = registered[0];
  assert.equal(tool.name, 'quickstart.orders.inspect.v1');
  assert.equal(tool.title, 'Inspect order');
  assert.deepEqual(tool.inputSchema, descriptorFixture.definition.inputSchema);
  assert.deepEqual(tool.annotations, {
    readOnlyHint: true,
    untrustedContentHint: false,
    consequentialHint: false,
  });
  assert.match(tool.description, /SurfaceRelay execution envelope v1/);
  assert.equal(signals[0].aborted, false);
});

test('test_void_dispatch_returns_envelope_undefined_output', async () => {
  await pollFor(() => registered.length === 1);
  const result = await registered[0].execute(
    { order_id: 101 },
    { signal: new AbortController().signal },
  );
  assert.deepEqual(result, {
    kind: 'surfacerelay.webmcp.execution.v1',
    status: 'returned',
    output: { kind: 'undefined' },
  });
  assert.deepEqual(calls, [{ method: 'inspectOrder', params: [101] }]);
});

test('test_forged_extra_input_is_rejected_before_dispatch', async () => {
  const result = await registered[0].execute(
    { order_id: 101, forged_tenant: 'other-tenant' },
    { signal: new AbortController().signal },
  );
  assert.equal(result.status, 'execution_failed');
  assert.equal(result.outcome, 'unknown');
  assert.deepEqual(calls, [{ method: 'inspectOrder', params: [101] }]);
});
