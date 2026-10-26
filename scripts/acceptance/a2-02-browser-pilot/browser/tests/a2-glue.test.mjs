/**
 * A2-02 B2 browser-wiring acceptance tests.
 *
 * Runs with the REAL published `@surfacerelay/browser-runtime@0.1.0-alpha.3`
 * package against a stubbed `document.modelContext` and a stubbed global
 * Livewire runtime. This proves the consumer glue and envelope return
 * semantics; it does NOT prove real Chrome `document.modelContext`
 * interoperability (that remains the separate A2-08 native proof).
 *
 * The descriptor document is REQUIRED: CI passes the server-issued document
 * emitted by A2BrowserEmissionTest through A2_DESCRIPTOR_FILE. A locally
 * generated descriptor is a developer harness input, never acceptance evidence.
 */

import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import {
  FilamentSelectionCoordinator,
} from '@surfacerelay/browser-runtime';
import { createA2ToolRegistration } from '../a2-surfacerelay-glue.mjs';

const TOOL_NAME = 'a2.orders.inspect.v1';
const EXECUTION_FAILED_MESSAGE =
  'Execution failed. The application outcome is unknown. Verify application state before considering a retry.';
const CANCELLED_MESSAGE = 'Execution was cancelled before driver dispatch.';

const descriptorPath = process.env.A2_DESCRIPTOR_FILE;
if (!descriptorPath) {
  throw new Error('A2_DESCRIPTOR_FILE is required: point it at the server-issued descriptor document.');
}
const { definition, binding } = JSON.parse(await readFile(descriptorPath, 'utf8'));

function stubDocument() {
  const registered = [];
  const removed = [];
  const signals = [];
  const doc = {
    modelContext: {
      async registerTool(tool, options) {
        registered.push(tool);
        signals.push(options.signal);
        options.signal.addEventListener('abort', () => removed.push(tool.name), { once: true });
      },
    },
  };
  return { doc, registered, removed, signals };
}

function stubWire({ callImpl, id = binding.target.componentId } = {}) {
  const calls = [];
  const wire = {
    $id: id,
    async $call(method, ...params) {
      calls.push({ method, params });
      if (callImpl) return callImpl(method, params);
      return undefined;
    },
    intercept(method, callback) {
      callback({ action: { cancel() {} }, onSend() {} });
      return () => {};
    },
  };
  return { wire, calls };
}

function livewireRootFor(wire) {
  return { Livewire: { find: (componentId) => (componentId === wire.$id ? wire : undefined) } };
}

async function executeTool(tool, input, signal = new AbortController().signal) {
  return tool.execute(input, { signal });
}

test('test_descriptor_document_matches_published_runtime_contract', async () => {
  assert.deepEqual(Object.keys({ definition, binding }).sort(), ['binding', 'definition']);
  assert.equal(definition.id, 'a2.orders.inspect');
  assert.equal(definition.version, 1);
  assert.deepEqual(Object.keys(definition), [
    'id', 'version', 'title', 'description', 'inputSchema', 'outputSchema', 'scope',
    'effect', 'risk', 'idempotency', 'outputSensitivity', 'outputContentTrust', 'contextRequirements',
  ]);
  assert.equal(definition.scope, 'page_scoped');
  assert.equal(definition.effect, 'read');
  assert.equal(definition.risk, 'low');
  assert.equal(definition.idempotency, 'none');
  assert.equal(definition.outputSensitivity, 'normal');
  assert.equal(definition.outputContentTrust, 'trusted_application_data');
  assert.deepEqual(definition.contextRequirements, ['authenticated_actor', 'tenant']);
  assert.deepEqual(Object.keys(binding).sort(), [
    'action', 'bindingId', 'driver', 'expiresAt', 'lifecycle', 'target',
  ]);
  assert.match(binding.bindingId, /^sr_[a-f0-9]{32}$/);
  assert.deepEqual(binding.action, { id: 'a2.orders.inspect', version: 1 });
  assert.equal(binding.driver, 'livewire');
  assert.equal(binding.lifecycle, 'component');
  assert.deepEqual(Object.keys(binding.target).sort(), ['componentId', 'inputOrder', 'method', 'requiredCount']);
  assert.equal(binding.target.method, 'inspectOrder');
  assert.deepEqual(binding.target.inputOrder, ['order_id']);
  assert.equal(binding.target.requiredCount, 1);
  assert.equal(binding.expiresAt, null);
});

test('test_registers_exactly_one_tool_with_published_projection_contract', async () => {
  const { doc, registered } = stubDocument();
  const handle = await createA2ToolRegistration({
    definition,
    binding,
    doc,
    livewireRoot: livewireRootFor(stubWire().wire),
  });

  assert.equal(handle.registered, true);
  assert.equal(handle.reason, null);
  assert.notEqual(handle.lease, null);
  assert.notEqual(handle.coordinator, null);
  assert.equal(registered.length, 1);

  const tool = registered[0];
  assert.equal(tool.name, TOOL_NAME);
  assert.equal(tool.title, definition.title);
  assert.equal(tool.description, definition.description + '\nSurfaceRelay execution envelope v1: inspect status and output. returned does not imply application success; inspect output.value for the application result. output.kind undefined means no returned value. execution_failed has unknown application outcome. Do not retry automatically; verify application state first. cancelled/not_dispatched means this callback did not invoke its driver.');
  assert.deepEqual(tool.inputSchema, definition.inputSchema);
  assert.deepEqual(tool.annotations, {
    readOnlyHint: true,
    untrustedContentHint: false,
    consequentialHint: false,
  });
});

test('test_unsupported_surface_fails_closed_without_registration', async () => {
  const registered = [];
  const doc = {};
  const handle = await createA2ToolRegistration({
    definition,
    binding,
    doc,
    livewireRoot: livewireRootFor(stubWire().wire),
  });

  assert.deepEqual(
    { registered: handle.registered, lease: handle.lease, coordinator: handle.coordinator, reason: handle.reason },
    { registered: false, lease: null, coordinator: null, reason: 'model_context_unavailable' },
  );
  assert.equal(registered.length, 0);
});

test('test_definition_binding_identity_mismatch_fails_closed_before_registration', async () => {
  const { doc, registered } = stubDocument();
  const mismatched = { ...binding, action: { id: 'a2.orders.inspect', version: 2 } };

  await assert.rejects(
    createA2ToolRegistration({ definition, binding: mismatched, doc, livewireRoot: {} }),
    /identity must match exactly/,
  );
  assert.equal(registered.length, 0);
});

test('test_void_dispatch_returns_envelope_undefined_output', async () => {
  const { doc, registered } = stubDocument();
  const { wire, calls } = stubWire();
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const result = await executeTool(tool, { order_id: 7 });

  assert.deepEqual(result, {
    kind: 'surfacerelay.webmcp.execution.v1',
    status: 'returned',
    output: { kind: 'undefined' },
  });
  assert.deepEqual(calls, [{ method: 'inspectOrder', params: [7] }]);
});

test('test_execution_failure_envelope_never_discloses_raw_error', async () => {
  const { doc, registered } = stubDocument();
  const { wire } = stubWire({
    callImpl: () => {
      throw new Error('TENANT-SECRET-42 leaked from the server exception');
    },
  });
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const result = await executeTool(tool, { order_id: 7 });

  assert.deepEqual(result, {
    kind: 'surfacerelay.webmcp.execution.v1',
    status: 'execution_failed',
    outcome: 'unknown',
    error: { code: 'execution_failed', message: EXECUTION_FAILED_MESSAGE },
  });
  assert.ok(!JSON.stringify(result).includes('TENANT-SECRET-42'));
});

test('test_stale_component_yields_execution_failed_envelope', async () => {
  const { doc, registered } = stubDocument();
  const emptyRoot = { Livewire: { find: () => undefined } };
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: emptyRoot,
  });
  const tool = registered[0];

  const result = await executeTool(tool, { order_id: 7 });

  assert.equal(result.kind, 'surfacerelay.webmcp.execution.v1');
  assert.equal(result.status, 'execution_failed');
  assert.equal(result.outcome, 'unknown');
  assert.equal(result.error.code, 'execution_failed');
});

test('test_expired_binding_yields_execution_failed_envelope', async () => {
  const { doc, registered } = stubDocument();
  const expired = {
    ...binding,
    expiresAt: '2020-01-01T00:00:00Z',
  };
  const { wire, calls } = stubWire();
  const handle = await createA2ToolRegistration({
    definition, binding: expired, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const result = await executeTool(tool, { order_id: 7 });

  assert.equal(result.kind, 'surfacerelay.webmcp.execution.v1');
  assert.equal(result.status, 'execution_failed');
  assert.equal(result.outcome, 'unknown');
  assert.equal(result.error.code, 'execution_failed');
  assert.deepEqual(calls, []);
});

test('test_forged_extra_input_is_rejected_before_dispatch', async () => {
  const { doc, registered } = stubDocument();
  const { wire, calls } = stubWire();
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const result = await executeTool(tool, { order_id: 7, forged_tenant: 'tenant-b' });

  assert.equal(result.kind, 'surfacerelay.webmcp.execution.v1');
  assert.equal(result.status, 'execution_failed');
  assert.equal(result.outcome, 'unknown');
  assert.deepEqual(calls, []);
});

test('test_missing_required_input_is_rejected_before_dispatch', async () => {
  const { doc, registered } = stubDocument();
  const { wire, calls } = stubWire();
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const result = await executeTool(tool, {});

  assert.equal(result.kind, 'surfacerelay.webmcp.execution.v1');
  assert.equal(result.status, 'execution_failed');
  assert.equal(result.outcome, 'unknown');
  assert.deepEqual(calls, []);
});

test('test_pre_aborted_signal_returns_cancelled_not_dispatched', async () => {
  const { doc, registered } = stubDocument();
  const { wire, calls } = stubWire();
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const controller = new AbortController();
  controller.abort();
  const result = await executeTool(tool, { order_id: 7 }, controller.signal);

  assert.deepEqual(result, {
    kind: 'surfacerelay.webmcp.execution.v1',
    status: 'cancelled',
    outcome: 'not_dispatched',
    error: { code: 'execution_cancelled', message: CANCELLED_MESSAGE },
  });
  assert.deepEqual(calls, []);
});

test('test_lease_dispose_aborts_registration_signal_for_unmount_cleanup', async () => {
  const { doc, registered, removed, signals } = stubDocument();
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(stubWire().wire),
  });

  assert.equal(registered.length, 1);
  assert.equal(signals[0].aborted, false);

  handle.lease.dispose();
  handle.lease.dispose(); // idempotent

  assert.equal(signals[0].aborted, true);
  assert.deepEqual(removed, [TOOL_NAME]);
});

test('test_concurrent_invocations_on_one_component_are_excluded_by_shared_coordinator', async () => {
  const { doc, registered } = stubDocument();
  const { wire } = stubWire({ callImpl: () => new Promise((resolve) => setTimeout(resolve, 10)) });
  const handle = await createA2ToolRegistration({
    definition, binding, doc, livewireRoot: livewireRootFor(wire),
  });
  const tool = registered[0];

  const first = executeTool(tool, { order_id: 7 });
  const second = executeTool(tool, { order_id: 8 });
  const results = await Promise.all([first, second]);

  const statuses = results.map((result) => result.status).sort();
  assert.deepEqual(statuses, ['execution_failed', 'returned']);
  const secondResult = results.find((result) => result.status === 'execution_failed');
  assert.equal(secondResult.outcome, 'unknown');
});

test('test_selection_sync_is_never_invoked_for_non_selection_action', async () => {
  const { doc, registered } = stubDocument();
  const { wire } = stubWire();
  const syncCalls = [];
  const handle = await createA2ToolRegistration({
    definition,
    binding,
    doc,
    livewireRoot: livewireRootFor(wire),
    selectionRuntime: { sync: (...args) => syncCalls.push(args) },
  });
  const tool = registered[0];

  await executeTool(tool, { order_id: 7 });

  assert.deepEqual(syncCalls, []);
});

test('test_coordinator_is_reusable_across_registrations_in_one_environment', async () => {
  const shared = new FilamentSelectionCoordinator();
  const { doc: docA, registered: registeredA } = stubDocument();
  const { doc: docB, registered: registeredB } = stubDocument();
  const handleA = await createA2ToolRegistration({
    definition, binding, doc: docA, coordinator: shared,
    livewireRoot: livewireRootFor(stubWire({ id: 'component-A' }).wire),
  });
  const handleB = await createA2ToolRegistration({
    definition, binding, doc: docB, coordinator: shared,
    livewireRoot: livewireRootFor(stubWire({ id: 'component-B' }).wire),
  });

  assert.equal(handleA.registered, true);
  assert.equal(handleB.registered, true);
  assert.equal(handleA.coordinator, shared);
  assert.equal(handleB.coordinator, shared);
  assert.equal(registeredA.length, 1);
  assert.equal(registeredB.length, 1);
});
