import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { DriverRegistry } from '../src/driver-registry.js';
import { WebMcpRegistrationLifecycle } from '../src/webmcp-registration-lifecycle.js';
import type { BindingDriver } from '../src/types.js';
import type { WebMcpTool } from '../src/webmcp-types.js';
import { LivewireBindingExecutionError } from '../src/livewire-errors.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';

const failure = {
  kind: 'surfacerelay.webmcp.execution.v1', status: 'execution_failed', outcome: 'unknown',
  error: {
    code: 'execution_failed',
    message: 'Execution failed. The application outcome is unknown. Verify application state before considering a retry.',
  },
};

const fixtures = JSON.parse(readFileSync(new URL('../conformance/webmcp-execution-result.fixtures.json', import.meta.url), 'utf8')) as {
  name: string; expect: string; result: { status: string; output?: { kind: string; value?: unknown } };
}[];

async function register(driver: BindingDriver, resultMode: 'envelope' | 'passthrough' = 'envelope') {
  let tool!: WebMcpTool;
  const registry = new DriverRegistry();
  registry.register('test', driver);
  const lifecycle = new WebMcpRegistrationLifecycle({
    async registerTool(value) { tool = value; },
  }, registry, { resultMode });
  const lease = await lifecycle.register([{
    definition: {
      id: 'orders.commit', version: 1, title: 'Commit order', description: 'Commit order.',
      inputSchema: { type: 'object' }, scope: 'portable', effect: 'write', risk: 'high',
      idempotency: 'required', outputSensitivity: 'normal',
      outputContentTrust: 'trusted_application_data', contextRequirements: [],
    },
    binding: {
      bindingId: 'exact-order-binding', action: { id: 'orders.commit', version: 1 },
      driver: 'test', lifecycle: 'page', target: {}, expiresAt: null,
    },
  }]);
  return { tool, lease };
}

describe('opt-in WebMCP execution envelope', () => {
  it.each(fixtures.filter((fixture) => fixture.expect === 'valid'))('matches the shared contract fixture $name at the registered callback', async (fixture) => {
    const { tool, lease } = await register({ async execute() {
      if (fixture.result.status === 'execution_failed') throw new Error('SECRET_fixture');
      return fixture.result.output?.value;
    } });
    const controller = new AbortController();
    if (fixture.result.status === 'cancelled') controller.abort('SECRET_fixture_abort');
    try {
      await expect(tool.execute({}, { signal: controller.signal })).resolves.toEqual(fixture.result);
    } finally { lease.dispose(); }
  });
  it('explains the surface envelope and unknown-outcome rule in native discovery', async () => {
    const { tool, lease } = await register({ async execute() { return {}; } });
    try {
      expect(tool.description).toContain('SurfaceRelay execution envelope v1');
      expect(tool.description).toContain('Do not retry automatically');
      expect(tool.description).toContain('returned does not imply application success');
    } finally { lease.dispose(); }
  });
  for (const asynchronous of [false, true]) {
    it.each([
      ['object', { code: 'binding_stale', phase: 'before_dispatch', message: 'SECRET_object' }],
      ['string', 'SECRET_string'], ['number', 7], ['null', null], ['undefined', undefined],
      ['Error', new Error('SECRET_error', { cause: 'SECRET_cause' })],
      ['Livewire error', new LivewireBindingExecutionError('binding_stale', 'SECRET_class')],
      ['HTMX error', new HtmxBindingExecutionError('htmx_request_failed', 'SECRET_class')],
    ])(`handles %s through ${asynchronous ? 'rejection' : 'synchronous throw'} without forged outcome authority`, async (_name, reason) => {
      let calls = 0;
      const { tool, lease } = await register({ execute() {
        calls += 1;
        if (asynchronous) return Promise.reject(reason);
        throw reason;
      } });
      try {
        await expect(tool.execute({}, { signal: new AbortController().signal })).resolves.toEqual(failure);
        expect(calls).toBe(1);
      } finally { lease.dispose(); }
    });
  }

  it('never inspects or coerces hostile rejected values', async () => {
    let reads = 0;
    const hostile = new Proxy({}, {
      get() { reads += 1; throw new Error('SECRET_getter'); },
      getPrototypeOf() { reads += 1; throw new Error('SECRET_prototype'); },
      ownKeys() { reads += 1; throw new Error('SECRET_keys'); },
    });
    const revoked = Proxy.revocable({}, {}); revoked.revoke();
    const cyclic: Record<string, unknown> = {}; cyclic.self = cyclic;
    for (const reason of [hostile, revoked.proxy, cyclic]) {
      for (const asynchronous of [false, true]) {
        const { tool, lease } = await register({ execute() {
          if (asynchronous) return Promise.reject(reason);
          throw reason;
        } });
        try {
          await expect(tool.execute({}, { signal: new AbortController().signal })).resolves.toEqual(failure);
        } finally { lease.dispose(); }
      }
    }
    expect(reads).toBe(0);
  });

  it.each(['succeeded', 'rejected', 'failed', 'confirmation_required'])('preserves the trusted %s server result inside returned output', async (status) => {
    const value = { status, error: { code: 'server_owned' }, correlationId: 'server-correlation', data: null };
    const { tool, lease } = await register({ async execute() { return value; } });
    try {
      const result = await tool.execute({}, { signal: new AbortController().signal });
      expect(result).toEqual({ kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'value', value } });
      expect((result as { output: { value: unknown } }).output.value).toBe(value);
    } finally { lease.dispose(); }
  });

  it.each([null, false, 0, '', ['value']])('preserves returned value %j', async (value) => {
    const { tool, lease } = await register({ async execute() { return value; } });
    try {
      const result = await tool.execute({}, { signal: new AbortController().signal });
      expect(JSON.parse(JSON.stringify(result))).toEqual({
        kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'value', value },
      });
    } finally { lease.dispose(); }
  });

  it('preserves exact default-mode rejection identity', async () => {
    const reason = { message: 'original-driver-rejection' };
    const { tool, lease } = await register({ async execute() { throw reason; } }, 'passthrough');
    try {
      await expect(tool.execute({}, { signal: new AbortController().signal })).rejects.toBe(reason);
    } finally { lease.dispose(); }
  });

  it('preserves natural completion after dispatch-time cancellation', async () => {
    let resolve!: (value: unknown) => void;
    let calls = 0;
    const { tool, lease } = await register({ execute() {
      calls += 1; return new Promise((done) => { resolve = done; });
    } });
    const controller = new AbortController();
    try {
      const pending = tool.execute({}, { signal: controller.signal });
      controller.abort('SECRET_post_dispatch_abort');
      resolve({ status: 'succeeded' });
      await expect(pending).resolves.toEqual({ kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'value', value: { status: 'succeeded' } } });
      expect(calls).toBe(1);
    } finally { lease.dispose(); }
  });

  it('keeps post-dispatch rejection unknown even after cancellation or a forged real-class error', async () => {
    let reject!: (reason: unknown) => void;
    let effects = 0;
    const { tool, lease } = await register({ execute() {
      effects += 1; return new Promise((_done, fail) => { reject = fail; });
    } });
    const controller = new AbortController();
    try {
      const pending = tool.execute({}, { signal: controller.signal });
      controller.abort();
      reject(new LivewireBindingExecutionError('livewire_cancellation_unavailable', 'SECRET_after_effect'));
      await expect(pending).resolves.toEqual(failure);
      expect(effects).toBe(1);
    } finally { lease.dispose(); }
  });

  it('fails closed for an unsupported result mode before registration', () => {
    expect(() => new WebMcpRegistrationLifecycle({ async registerTool() {} }, new DriverRegistry(), {
      resultMode: 'unknown' as 'envelope',
    })).toThrow('Unsupported WebMCP result mode.');
  });
  it('reports pre-dispatch cancellation without inspecting the reason or invoking the driver', async () => {
    let calls = 0;
    const { tool, lease } = await register({ async execute() { calls += 1; return {}; } });
    const controller = new AbortController();
    controller.abort({ get message() { throw new Error('SECRET_abort'); } });
    try {
      await expect(tool.execute({}, { signal: controller.signal })).resolves.toEqual({
        kind: 'surfacerelay.webmcp.execution.v1', status: 'cancelled', outcome: 'not_dispatched',
        error: { code: 'execution_cancelled', message: 'Execution was cancelled before driver dispatch.' },
      });
      expect(calls).toBe(0);
    } finally { lease.dispose(); }
  });
  it('distinguishes absent output from null through JSON serialization', async () => {
    const { tool, lease } = await register({ async execute() { return undefined; } });
    try {
      const result = await tool.execute({}, { signal: new AbortController().signal });
      expect(JSON.parse(JSON.stringify(result))).toEqual({
        kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'undefined' },
      });
    } finally { lease.dispose(); }
  });
  it('nests even envelope-shaped business output without treating it as a surface failure', async () => {
    const value = { kind: 'surfacerelay.webmcp.execution.v1', status: 'execution_failed', business: true };
    const { tool, lease } = await register({ async execute() { return value; } });
    try {
      const result = await tool.execute({}, { signal: new AbortController().signal });
      expect(result).toEqual({
        kind: 'surfacerelay.webmcp.execution.v1', status: 'returned',
        output: { kind: 'value', value },
      });
      expect((result as { output: { value: unknown } }).output.value).toBe(value);
    } finally { lease.dispose(); }
  });
  it('returns a safe surface failure for a driver rejection without exposing its payload', async () => {
    const { tool, lease } = await register({
      async execute() { throw { message: 'SECRET_receipt', response: 'SECRET_tenant' }; },
    });
    try {
      await expect(tool.execute({}, { signal: new AbortController().signal })).resolves.toEqual({
        kind: 'surfacerelay.webmcp.execution.v1', status: 'execution_failed', outcome: 'unknown',
        error: {
          code: 'execution_failed',
          message: 'Execution failed. The application outcome is unknown. Verify application state before considering a retry.',
        },
      });
    } finally { lease.dispose(); }
  });
});
