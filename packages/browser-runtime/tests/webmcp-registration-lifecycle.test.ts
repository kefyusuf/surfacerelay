import { describe, expect, it, vi } from 'vitest';
import { DriverRegistry } from '../src/driver-registry.js';
import {
  WebMcpRegistrationLifecycle,
  type WebMcpRegistrationLease,
} from '../src/webmcp-registration-lifecycle.js';
import type { BoundActionTool } from '../src/webmcp-tool-projection.js';
import type { ActionDefinition, BindingDriver, RuntimeBinding } from '../src/types.js';
import type {
  WebMcpModelContext,
  WebMcpRegisterToolOptions,
  WebMcpTool,
} from '../src/webmcp-types.js';

interface RegistrationCall {
  tool: WebMcpTool;
  options: WebMcpRegisterToolOptions;
}

class RecordingModelContext implements WebMcpModelContext {
  readonly calls: RegistrationCall[] = [];
  onRegister?: (tool: WebMcpTool, options: WebMcpRegisterToolOptions) => Promise<void>;

  async registerTool(tool: WebMcpTool, options: WebMcpRegisterToolOptions): Promise<void> {
    this.calls.push({ tool, options });
    await this.onRegister?.(tool, options);
  }
}

function actionDefinition(
  id = 'orders.get',
  version = 1,
  overrides: Partial<ActionDefinition> = {},
): ActionDefinition {
  return {
    id,
    version,
    title: `Action ${id}`,
    description: `Executes ${id}`,
    inputSchema: { type: 'object' },
    scope: 'portable',
    effect: 'read',
    risk: 'low',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
    ...overrides,
  };
}

function runtimeBinding(
  definition: ActionDefinition,
  overrides: Partial<RuntimeBinding> = {},
): RuntimeBinding {
  return {
    bindingId: `binding-${definition.id}-${definition.version}`,
    action: { id: definition.id, version: definition.version },
    driver: 'livewire',
    lifecycle: 'component',
    target: { componentId: 'component-1', method: 'invoke' },
    expiresAt: null,
    ...overrides,
  };
}

function candidate(
  id = 'orders.get',
  version = 1,
  bindingOverrides: Partial<RuntimeBinding> = {},
  definitionOverrides: Partial<ActionDefinition> = {},
): BoundActionTool {
  const definition = actionDefinition(id, version, definitionOverrides);
  return {
    definition,
    binding: runtimeBinding(definition, bindingOverrides),
  };
}

function registeredDriver(): {
  driver: BindingDriver;
  execute: ReturnType<typeof vi.fn>;
} {
  const execute = vi.fn(async () => ({ ok: true }));
  const driver: BindingDriver = { execute };
  return { driver, execute };
}

function registryWithLivewire(driver?: BindingDriver): DriverRegistry {
  const registry = new DriverRegistry();
  registry.register('livewire', driver ?? registeredDriver().driver);
  return registry;
}

function deferred<T>(): {
  promise: Promise<T>;
  resolve(value: T): void;
  reject(reason?: unknown): void;
} {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((res, rej) => {
    resolve = res;
    reject = rej;
  });
  return { promise, resolve, reject };
}

async function nextMicrotask(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
}

describe('WebMCP registration lifecycle', () => {
  it('rejects definition/binding identity mismatch before browser registration', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());
    const value = candidate('orders.refund', 2, {
      action: { id: 'orders.refund', version: 1 },
    });

    await expect(lifecycle.register([value]))
      .rejects.toThrow('Action definition and binding identity must match exactly.');
    expect(context.calls).toHaveLength(0);
  });

  it('rejects unsupported drivers before browser registration', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, new DriverRegistry());

    await expect(lifecycle.register([candidate()]))
      .rejects.toThrow('Unsupported binding driver.');
    expect(context.calls).toHaveLength(0);
  });

  it('rejects invalid projected names before browser registration', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());
    const id = `${'a'.repeat(62)}.${'b'.repeat(62)}`;

    await expect(lifecycle.register([candidate(id, 1234)]))
      .rejects.toThrow('Projected WebMCP tool name is invalid.');
    expect(context.calls).toHaveLength(0);
  });

  it('rejects duplicate projected action identity as ambiguous before registration', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());
    const first = candidate('prep_list.add_item', 1, { bindingId: 'binding-a' });
    const second = candidate('prep_list.add_item', 1, {
      bindingId: 'binding-b',
      target: { componentId: 'component-b', method: 'addItem' },
    });

    await expect(lifecycle.register([first, second]))
      .rejects.toThrow('Duplicate WebMCP tool identity in registration snapshot.');
    expect(context.calls).toHaveLength(0);
  });

  it('preflights driver support without executing the driver', async () => {
    const context = new RecordingModelContext();
    const { driver, execute } = registeredDriver();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire(driver));

    const lease = await lifecycle.register([candidate()]);

    expect(execute).not.toHaveBeenCalled();
    lease.dispose();
  });

  it('registers in deterministic name order and strictly sequentially', async () => {
    const context = new RecordingModelContext();
    const firstRegistration = deferred<void>();
    context.onRegister = async (tool) => {
      if (tool.name === 'a.one.v1') {
        return firstRegistration.promise;
      }
    };
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());

    const pending = lifecycle.register([
      candidate('b.two'),
      candidate('a.one'),
    ]);

    await nextMicrotask();
    expect(context.calls.map((call) => call.tool.name)).toEqual(['a.one.v1']);

    firstRegistration.resolve(undefined);
    const lease = await pending;

    expect(context.calls.map((call) => call.tool.name))
      .toEqual(['a.one.v1', 'b.two.v1']);
    lease.dispose();
  });

  it('uses one registration signal for every tool in one successful snapshot', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());

    const lease = await lifecycle.register([
      candidate('b.two'),
      candidate('a.one'),
    ]);

    expect(context.calls).toHaveLength(2);
    expect(context.calls[0].options.signal).toBe(context.calls[1].options.signal);
    expect(context.calls[0].options.signal.aborted).toBe(false);
    lease.dispose();
  });

  it('returns an idempotent lease whose disposal aborts registration authority', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());
    const lease: WebMcpRegistrationLease = await lifecycle.register([candidate()]);
    const signal = context.calls[0].options.signal;
    let abortEvents = 0;
    signal.addEventListener('abort', () => { abortEvents += 1; });

    lease.dispose();
    lease.dispose();

    expect(signal.aborted).toBe(true);
    expect(abortEvents).toBe(1);
  });

  it('returns a disposable lease for an empty snapshot without browser calls', async () => {
    const context = new RecordingModelContext();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());

    const lease = await lifecycle.register([]);

    expect(context.calls).toHaveLength(0);
    expect(() => lease.dispose()).not.toThrow();
  });

  it('aborts earlier registrations and rethrows the exact original error on partial failure', async () => {
    const context = new RecordingModelContext();
    const failure = new DOMException('denied', 'NotAllowedError');
    context.onRegister = async (tool) => {
      if (tool.name === 'b.two.v1') throw failure;
    };
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire());

    await expect(lifecycle.register([
      candidate('a.one'),
      candidate('b.two'),
    ])).rejects.toBe(failure);

    expect(context.calls).toHaveLength(2);
    expect(context.calls[0].options.signal).toBe(context.calls[1].options.signal);
    expect(context.calls[0].options.signal.aborted).toBe(true);
  });

  it('dispatches execution through the exact binding driver with a distinct execution signal', async () => {
    const context = new RecordingModelContext();
    const { driver, execute } = registeredDriver();
    const lifecycle = new WebMcpRegistrationLifecycle(context, registryWithLivewire(driver));
    const value = candidate('prep_list.add_item', 1, {
      bindingId: 'exact-binding',
      target: { componentId: 'component-42', method: 'addItem' },
    });
    const lease = await lifecycle.register([value]);
    const registrationSignal = context.calls[0].options.signal;
    const executionController = new AbortController();
    const input = { name: 'passport' };

    await expect(context.calls[0].tool.execute(input, {
      signal: executionController.signal,
    })).resolves.toEqual({ ok: true });

    expect(execute).toHaveBeenCalledTimes(1);
    expect(execute).toHaveBeenCalledWith(
      value.binding,
      input,
      { signal: executionController.signal },
    );
    expect(executionController.signal).not.toBe(registrationSignal);
    lease.dispose();
  });
});
