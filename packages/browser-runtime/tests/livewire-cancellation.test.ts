import { describe, expect, it, vi } from 'vitest';
import { LivewireBrowserDriver } from '../src/livewire-browser-driver.js';
import { LivewireBindingExecutionError } from '../src/livewire-errors.js';
import type {
  LivewireActionHandle,
  LivewireActionInterceptorContext,
  LivewireBrowserRuntime,
  LivewireWire,
} from '../src/livewire-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

const now = new Date('2026-09-07T00:00:00.000Z');

function binding(): RuntimeBinding {
  return {
    bindingId: 'binding-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: {
      componentId: 'component-1',
      method: 'addItem',
      inputOrder: ['name'],
      requiredCount: 1,
    },
    expiresAt: null,
  };
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

interface InterceptorEntry {
  method: string;
  callback: (context: LivewireActionInterceptorContext) => void;
}

class InterceptingWire implements LivewireWire {
  readonly $id = 'component-1';
  readonly callResult = deferred<unknown>();
  readonly internalCancellation = new Error('livewire internal cancellation');
  readonly cancel = vi.fn(() => this.callResult.reject(this.internalCancellation));
  readonly $call = vi.fn((method: string, ..._params: unknown[]) => {
    const action: LivewireActionHandle = { cancel: this.cancel };
    const onSendCallbacks: Array<() => void> = [];
    const context: LivewireActionInterceptorContext = {
      action,
      onSend(callback) {
        onSendCallbacks.push(callback);
      },
    };

    this.currentOnSend = onSendCallbacks;
    this.interceptors.forEach((entry) => {
      if (entry.method === method) entry.callback(context);
    });

    return this.callResult.promise;
  });

  readonly interceptors: InterceptorEntry[] = [];
  readonly unsubscribeCalls: Array<ReturnType<typeof vi.fn>> = [];
  currentOnSend: Array<() => void> = [];
  trailingInterceptor?: () => void;
  addTrailingOnNextRegistration = false;

  intercept(
    method: string,
    callback: (context: LivewireActionInterceptorContext) => void,
  ): () => void {
    const entry: InterceptorEntry = { method, callback };
    this.interceptors.push(entry);

    if (this.addTrailingOnNextRegistration) {
      this.addTrailingOnNextRegistration = false;
      this.interceptors.push({
        method,
        callback: () => this.trailingInterceptor?.(),
      });
    }

    const unsubscribe = vi.fn(() => {
      const index = this.interceptors.indexOf(entry);
      if (index >= 0) this.interceptors.splice(index, 1);
    });
    this.unsubscribeCalls.push(unsubscribe);
    return unsubscribe;
  }

  addPriorInterceptor(
    method: string,
    callback: (context: LivewireActionInterceptorContext) => void,
  ): void {
    this.interceptors.push({ method, callback });
  }

  send(): void {
    for (const callback of [...this.currentOnSend]) callback();
  }
}

function runtime(found: LivewireWire | undefined): LivewireBrowserRuntime & { find: ReturnType<typeof vi.fn> } {
  return {
    find: vi.fn(() => found),
  };
}

function driver(found: LivewireWire | undefined): {
  driver: LivewireBrowserDriver;
  runtime: ReturnType<typeof runtime>;
} {
  const livewire = runtime(found);
  return {
    driver: new LivewireBrowserDriver(livewire, { now: () => now }),
    runtime: livewire,
  };
}

async function expectCode(promise: Promise<unknown>, code: string): Promise<void> {
  try {
    await promise;
    throw new Error('expected LivewireBindingExecutionError');
  } catch (error) {
    expect(error).toBeInstanceOf(LivewireBindingExecutionError);
    expect((error as LivewireBindingExecutionError).code).toBe(code);
  }
}

async function nextMicrotask(): Promise<void> {
  await Promise.resolve();
  await Promise.resolve();
}

describe('Livewire cancellation propagation', () => {
  it('rejects an already-aborted direct-driver signal before Livewire lookup', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const reason = new Error('caller stopped before invocation');
    const controller = new AbortController();
    controller.abort(reason);

    await expect(fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    )).rejects.toBe(reason);

    expect(fixture.runtime.find).not.toHaveBeenCalled();
    expect(targetWire.$call).not.toHaveBeenCalled();
  });

  it('fails before $call when a cancellation-aware runtime has no documented interceptor capability', async () => {
    const targetWire: LivewireWire = {
      $id: 'component-1',
      $call: vi.fn(async () => ({ ok: true })),
    };
    const fixture = driver(targetWire);

    await expectCode(fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: new AbortController().signal },
    ), 'livewire_cancellation_unavailable');

    expect(targetWire.$call).not.toHaveBeenCalled();
  });

  it('cancels the exact captured action once before onSend and surfaces the caller abort reason', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const reason = new Error('caller stopped queued action');

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    controller.abort(reason);

    await expect(pending).rejects.toBe(reason);
    expect(targetWire.cancel).toHaveBeenCalledTimes(1);
  });

  it('cancels when abort is observed synchronously before SurfaceRelay captures the action', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const reason = new Error('prior interceptor stopped caller');
    targetWire.addPriorInterceptor('addItem', () => controller.abort(reason));

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    queueMicrotask(() => targetWire.callResult.reject(targetWire.internalCancellation));

    await expect(pending).rejects.toBe(reason);
    expect(targetWire.cancel).toHaveBeenCalledTimes(1);
  });

  it('treats onSend as the dispatch frontier and preserves natural success after a later abort', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const reason = new Error('caller stopped observing');
    const output = { itemId: 'item-1' };

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    targetWire.send();
    controller.abort(reason);
    targetWire.callResult.resolve(output);

    await expect(pending).resolves.toBe(output);
    expect(targetWire.cancel).not.toHaveBeenCalled();
  });

  it('preserves the exact natural Livewire failure after dispatch even when caller later aborts', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const original = new Error('authorization denied');

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    targetWire.send();
    controller.abort(new Error('caller stopped observing'));
    targetWire.callResult.reject(original);

    await expect(pending).rejects.toBe(original);
    expect(targetWire.cancel).not.toHaveBeenCalled();
  });

  it('does not synchronously unsubscribe while Livewire iterates interceptors', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const trailing = vi.fn();
    targetWire.trailingInterceptor = trailing;
    targetWire.addTrailingOnNextRegistration = true;

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: new AbortController().signal },
    );

    expect(trailing).toHaveBeenCalledTimes(1);
    expect(targetWire.unsubscribeCalls).toHaveLength(1);
    expect(targetWire.unsubscribeCalls[0]).not.toHaveBeenCalled();

    await nextMicrotask();
    expect(targetWire.unsubscribeCalls[0]).toHaveBeenCalledTimes(1);

    targetWire.send();
    targetWire.callResult.resolve({ ok: true });
    await pending;
  });

  it('removes the execution-local abort listener on natural completion', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, 'removeEventListener');

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    targetWire.send();
    targetWire.callResult.resolve({ ok: true });
    await pending;

    expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
  });

  it('removes the execution-local abort listener after pre-dispatch cancellation', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const reason = new Error('caller stopped queued action');
    const remove = vi.spyOn(controller.signal, 'removeEventListener');

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    controller.abort(reason);

    await expect(pending).rejects.toBe(reason);
    expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
  });

  it('removes the execution-local abort listener after natural failure', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();
    const remove = vi.spyOn(controller.signal, 'removeEventListener');
    const original = new Error('server failure');

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    targetWire.send();
    targetWire.callResult.reject(original);

    await expect(pending).rejects.toBe(original);
    expect(remove).toHaveBeenCalledWith('abort', expect.any(Function));
  });

  it('does not retain its interceptor or signal authority for a later same-method invocation', async () => {
    const targetWire = new InterceptingWire();
    const fixture = driver(targetWire);
    const controller = new AbortController();

    const pending = fixture.driver.execute(
      binding(),
      { name: 'passport' },
      { signal: controller.signal },
    );

    targetWire.send();
    targetWire.callResult.resolve({ ok: true });
    await pending;
    await nextMicrotask();

    expect(targetWire.interceptors).toHaveLength(0);
    expect(targetWire.cancel).not.toHaveBeenCalled();

    await targetWire.$call('addItem', 'later-human-call');
    controller.abort(new Error('late abort from completed execution'));

    expect(targetWire.cancel).not.toHaveBeenCalled();
  });
});
