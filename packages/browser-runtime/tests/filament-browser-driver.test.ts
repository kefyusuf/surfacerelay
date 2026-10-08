import { describe, expect, it, vi } from 'vitest';
import { FilamentBrowserDriver, FilamentSelectionCoordinator } from '../src/filament-browser-driver.js';
import type { BoundActionTool } from '../src/webmcp-tool-projection.js';
import { DriverRegistry } from '../src/driver-registry.js';
import { WebMcpRegistrationLifecycle } from '../src/webmcp-registration-lifecycle.js';
import type { WebMcpTool } from '../src/webmcp-types.js';

function tool(componentId = 'component-1', selection = true): BoundActionTool {
  return {
    definition: {
      id: 'orders.refund', version: 1, title: 'Refund', description: 'Refund selection',
      inputSchema: {}, scope: 'page_scoped', effect: 'reversible_write', risk: 'moderate',
      contextRequirements: selection ? ['current_selection'] : [],
      idempotency: 'none',
      outputSensitivity: 'normal', outputContentTrust: 'trusted_application_data',
    },
    binding: {
      bindingId: 'binding-1', action: { id: 'orders.refund', version: 1 },
      driver: 'livewire', lifecycle: 'component', expiresAt: null,
      target: { componentId, method: 'refund', inputOrder: [], requiredCount: 0 },
    },
  };
}

describe('FilamentBrowserDriver', () => {
  it('rejects nonfinite expiry at registration without invoking the framework', () => {
    const candidate = tool(); candidate.binding.expiresAt = NaN as any;
    const find = vi.fn();
    expect(() => new FilamentBrowserDriver({ find }, {
      tools: [candidate], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    })).toThrow('invalid');
    expect(find).not.toHaveBeenCalled();
  });

  it('rejects accessor descriptors without executing the getter', () => {
    const candidate = tool(); const getter = vi.fn(() => 'component-1');
    Object.defineProperty(candidate.binding.target, 'componentId', { get: getter, enumerable: true });
    expect(() => new FilamentBrowserDriver({ find: vi.fn() }, {
      tools: [candidate], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    })).toThrow('invalid');
    expect(getter).not.toHaveBeenCalled();
  });

  it('rejects custom serialization without executing it', () => {
    const candidate = tool(); const serializer = vi.fn(() => ({}));
    candidate.binding.target.toJSON = serializer;
    expect(() => new FilamentBrowserDriver({ find: vi.fn() }, {
      tools: [candidate], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    })).toThrow('invalid');
    expect(serializer).not.toHaveBeenCalled();
  });

  it('rejects an added undefined target key after registration', async () => {
    const candidate = tool(); const find = vi.fn();
    const driver = new FilamentBrowserDriver({ find }, {
      tools: [candidate], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    });
    candidate.binding.target.extra = undefined;
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toThrow('changed');
    expect(find).not.toHaveBeenCalled();
  });
  it.each(['passthrough', 'envelope'] as const)('preserves %s results through the registered public driver boundary', async resultMode => {
    const candidate = tool('component-1', false);
    const value = { status: 'rejected', error: { code: 'authorization_denied' } };
    const sync = vi.fn(); let capture!: (context: any) => void;
    const wire = { $id: 'component-1',
      intercept: (_method: string, callback: (context: any) => void) => { capture = callback; return () => {}; },
      $call: vi.fn(async () => { capture({ action: { cancel() {} }, onSend: (sent: () => void) => sent() }); return value; }),
    };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    const registry = new DriverRegistry(); registry.register('livewire', driver);
    let registered!: WebMcpTool;
    const lifecycle = new WebMcpRegistrationLifecycle({ registerTool: value => { registered = value; } }, registry, { resultMode });
    const lease = await lifecycle.register([candidate]);
    const result = await registered.execute({}, { signal: new AbortController().signal });
    if (resultMode === 'passthrough') expect(result).toBe(value);
    else expect(result).toEqual({ kind: 'surfacerelay.webmcp.execution.v1', status: 'returned', output: { kind: 'value', value } });
    expect(sync).not.toHaveBeenCalled(); expect(wire.$call).toHaveBeenCalledOnce();
    await lease.dispose();
  });
  it('never normalizes an undefined extra target field into a valid binding', async () => {
    const candidate = tool(); candidate.binding.target.extra = undefined;
    const call = vi.fn(async () => 'unsafe'); const sync = vi.fn();
    const driver = new FilamentBrowserDriver({ find: () => ({ $id: 'component-1', $call: call }) }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toBeDefined();
    expect(call).not.toHaveBeenCalled(); expect(sync).not.toHaveBeenCalled();
  });

  it('rejects null expiry mutated to NaN rather than treating it as unchanged', async () => {
    const candidate = tool(); const call = vi.fn(async () => 'unsafe'); const sync = vi.fn();
    const driver = new FilamentBrowserDriver({ find: () => ({ $id: 'component-1', $call: call }) }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    candidate.binding.expiresAt = NaN as any;
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toBeDefined();
    expect(call).not.toHaveBeenCalled(); expect(sync).not.toHaveBeenCalled();
  });
  it.each([
    ['expired', { expiresAt: '2000-01-01T00:00:00Z' }, {}, {}],
    ['malformed target', { target: {} }, {}, {}],
    ['reserved method', { target: { componentId: 'component-1', method: 'set', inputOrder: [], requiredCount: 0 } }, {}, {}],
    ['unmapped input', {}, { unknown: true }, {}],
    ['missing cancellation', {}, {}, { signal: new AbortController().signal }],
    ['pre-aborted', {}, {}, { signal: AbortSignal.abort('stop') }],
  ])('rejects %s before any selection write or dispatch', async (_name, overrides, input, context) => {
    const candidate = tool(); Object.assign(candidate.binding, overrides);
    const wire = { $id: 'component-1', $call: vi.fn(async () => null) };
    const selectionRuntime = { sync: vi.fn() };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(candidate.binding, input, context)).rejects.toBeDefined();
    expect(selectionRuntime.sync).not.toHaveBeenCalled(); expect(wire.$call).not.toHaveBeenCalled();
  });

  it.each([undefined, { $id: 'foreign', $call: async () => null }, { $id: 'component-1' }])(
    'rejects stale or non-callable components before selection %#', async wire => {
      const candidate = tool(); const selectionRuntime = { sync: vi.fn() };
      const driver = new FilamentBrowserDriver({ find: () => wire as any }, {
        tools: [candidate], selectionRuntime, coordinator: new FilamentSelectionCoordinator(),
      });
      await expect(driver.execute(candidate.binding, {}, {})).rejects.toBeDefined();
      expect(selectionRuntime.sync).not.toHaveBeenCalled();
    },
  );

  it.each([
    (b: BoundActionTool['binding']) => { b.bindingId = 'changed'; },
    (b: BoundActionTool['binding']) => { b.action.version = 2; },
    (b: BoundActionTool['binding']) => { b.action.id = 'foreign.action'; },
    (b: BoundActionTool['binding']) => { b.driver = 'htmx'; },
    (b: BoundActionTool['binding']) => { b.lifecycle = 'page'; },
    (b: BoundActionTool['binding']) => { b.expiresAt = '2099-01-01T00:00:00Z'; },
    (b: BoundActionTool['binding']) => { b.target.method = 'different'; },
    (b: BoundActionTool['binding']) => { b.target.inputOrder = ['receipt']; },
    (b: BoundActionTool['binding']) => { b.target.requiredCount = 1; },
  ])('rejects changed registered descriptors before lookup %#', async mutate => {
    const candidate = tool(); const find = vi.fn(); const sync = vi.fn();
    const driver = new FilamentBrowserDriver({ find }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    mutate(candidate.binding);
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toThrow('changed');
    expect(find).not.toHaveBeenCalled(); expect(sync).not.toHaveBeenCalled();
  });

  it('captures selection policy and options without freezing caller objects', async () => {
    const candidate = tool(); const sync = vi.fn(); const replacement = vi.fn();
    const wire = { $id: 'component-1', $call: vi.fn(async () => 'done') };
    const options = { tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator() };
    const driver = new FilamentBrowserDriver({ find: () => wire }, options);
    candidate.definition.contextRequirements.length = 0; options.tools.length = 0;
    options.selectionRuntime = { sync: replacement };
    expect(await driver.execute(candidate.binding, {}, {})).toBe('done');
    expect(sync).toHaveBeenCalledOnce(); expect(replacement).not.toHaveBeenCalled();
  });

  it('excludes calls across drivers sharing a coordinator and never releases another call guard', async () => {
    const selection = tool(); const record = tool('component-1', false);
    record.binding.bindingId = 'binding-record';
    let finish!: (value: unknown) => void;
    const pending = new Promise(resolve => { finish = resolve; });
    const wire = { $id: 'component-1', $call: vi.fn(() => pending) };
    const sync = vi.fn(); const coordinator = new FilamentSelectionCoordinator();
    const first = new FilamentBrowserDriver({ find: () => wire }, { tools: [selection], selectionRuntime: { sync }, coordinator });
    const second = new FilamentBrowserDriver({ find: () => wire }, { tools: [record], selectionRuntime: { sync }, coordinator });
    const running = first.execute(selection.binding, {}, {});
    await expect(second.execute(record.binding, {}, {})).rejects.toThrow('pending');
    await expect(first.execute(selection.binding, {}, {})).rejects.toThrow('pending');
    expect(sync).toHaveBeenCalledOnce(); expect(wire.$call).toHaveBeenCalledOnce();
    finish('done'); expect(await running).toBe('done');
    expect(await second.execute(record.binding, {}, {})).toBe('done');
    expect(sync).toHaveBeenCalledOnce();
  });

  it('holds occupancy when exact-action capture rejects before the underlying call settles', async () => {
    const candidate = tool(); let finish!: (value: unknown) => void;
    const pending = new Promise(resolve => { finish = resolve; });
    const wire = { $id: 'component-1', $call: vi.fn(() => pending), intercept: vi.fn(() => () => {}) };
    const sync = vi.fn();
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(candidate.binding, {}, { signal: new AbortController().signal }))
      .rejects.toMatchObject({ code: 'livewire_cancellation_unavailable' });
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toThrow('pending');
    expect(wire.$call).toHaveBeenCalledOnce(); expect(sync).toHaveBeenCalledOnce();
    finish('done'); await pending;
    expect(await driver.execute(candidate.binding, {}, {})).toBe('done');
  });

  it('does not release an initiated action when the caller aborts after send', async () => {
    const candidate = tool(); let finish!: (value: unknown) => void;
    const pending = new Promise(resolve => { finish = resolve; });
    let capture!: (context: any) => void; const cancel = vi.fn(); const unsubscribe = vi.fn();
    const wire = { $id: 'component-1',
      intercept: vi.fn((_method, callback) => { capture = callback; return unsubscribe; }),
      $call: vi.fn(() => { capture({ action: { cancel }, onSend: (sent: () => void) => sent() }); return pending; }),
    };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    });
    const controller = new AbortController(); const running = driver.execute(candidate.binding, {}, { signal: controller.signal });
    controller.abort('late');
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toThrow('pending');
    expect(cancel).not.toHaveBeenCalled(); finish('effect');
    expect(await running).toBe('effect'); expect(unsubscribe).toHaveBeenCalledOnce();
  });

  it('allows different components to execute while another component is pending', async () => {
    const first = tool(); const second = tool('component-2'); second.binding.bindingId = 'second';
    let finish!: (value: unknown) => void; const pending = new Promise(resolve => { finish = resolve; });
    const driver = new FilamentBrowserDriver({ find: id => ({ $id: id, $call: () => id === 'component-1' ? pending : Promise.resolve('independent') }) }, {
      tools: [first, second], selectionRuntime: { sync: vi.fn() }, coordinator: new FilamentSelectionCoordinator(),
    });
    const running = driver.execute(first.binding, {}, {});
    expect(await driver.execute(second.binding, {}, {})).toBe('independent');
    finish('first'); expect(await running).toBe('first');
  });

  it.each(['sync throw', 'call throw', 'call rejection'])('releases its guard after %s and preserves the failure', async mode => {
    const candidate = tool(); const error = new Error('boundary failure');
    const sync = vi.fn(); const call = vi.fn(async () => 'recovered');
    if (mode === 'sync throw') sync.mockImplementationOnce(() => { throw error; });
    if (mode === 'call throw') call.mockImplementationOnce(() => { throw error; });
    if (mode === 'call rejection') call.mockRejectedValueOnce(error);
    const driver = new FilamentBrowserDriver({ find: () => ({ $id: 'component-1', $call: call }) }, {
      tools: [candidate], selectionRuntime: { sync }, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toBe(error);
    if (mode === 'sync throw') expect(call).not.toHaveBeenCalled();
    expect(await driver.execute(candidate.binding, {}, {})).toBe('recovered');
  });

  it('honors cancellation triggered during synchronous selection without dispatch', async () => {
    const candidate = tool(); const controller = new AbortController(); const call = vi.fn();
    const driver = new FilamentBrowserDriver({ find: () => ({ $id: 'component-1', $call: call, intercept: () => () => {} }) }, {
      tools: [candidate], selectionRuntime: { sync: () => controller.abort('during-sync') }, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(candidate.binding, {}, { signal: controller.signal })).rejects.toBe('during-sync');
    expect(call).not.toHaveBeenCalled();
  });
  it('rejects conflicting exposure policies for the same exact binding', () => {
    const candidate = tool();
    const conflicting = { binding: candidate.binding, definition: { ...candidate.definition, contextRequirements: [] } };
    expect(() => new FilamentBrowserDriver({ find: () => undefined }, {
      tools: [candidate, conflicting], selectionRuntime: { sync: vi.fn() },
      coordinator: new FilamentSelectionCoordinator(),
    })).toThrow('conflicting');
  });
  it('rejects target mutation after registration without changing application-owned objects', async () => {
    const candidate = tool();
    const wire = { $id: 'component-2', $call: vi.fn(async () => 'unexpected') };
    const selectionRuntime = { sync: vi.fn() };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime, coordinator: new FilamentSelectionCoordinator(),
    });
    expect(Object.isFrozen(candidate.binding)).toBe(false);
    candidate.binding.target.componentId = 'component-2';
    await expect(driver.execute(candidate.binding, {}, {})).rejects.toThrow('changed');
    expect(selectionRuntime.sync).not.toHaveBeenCalled();
    expect(wire.$call).not.toHaveBeenCalled();
  });
  it('rejects an unregistered same-value binding clone before selection or dispatch', async () => {
    const candidate = tool();
    const wire = { $id: 'component-1', $call: vi.fn(async () => 'unexpected') };
    const selectionRuntime = { sync: vi.fn() };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime, coordinator: new FilamentSelectionCoordinator(),
    });
    await expect(driver.execute(structuredClone(candidate.binding), {}, {})).rejects.toThrow('registered');
    expect(selectionRuntime.sync).not.toHaveBeenCalled();
    expect(wire.$call).not.toHaveBeenCalled();
  });
  it('synchronizes selection before dispatch and preserves the exact result', async () => {
    const candidate = tool();
    const order: string[] = [];
    const result = { status: 'confirmation_required' };
    const wire = { $id: 'component-1', $call: vi.fn(async () => { order.push('call'); return result; }) };
    const selectionRuntime = { sync: vi.fn((id, resolved) => {
      expect(id).toBe('component-1'); expect(resolved).toBe(wire); order.push('sync');
    }) };
    const driver = new FilamentBrowserDriver({ find: () => wire }, {
      tools: [candidate], selectionRuntime, coordinator: new FilamentSelectionCoordinator(),
    });
    expect(await driver.execute(candidate.binding, {}, {})).toBe(result);
    expect(order).toEqual(['sync', 'call']);
    expect(wire.$call).toHaveBeenCalledWith('refund');
  });
});
