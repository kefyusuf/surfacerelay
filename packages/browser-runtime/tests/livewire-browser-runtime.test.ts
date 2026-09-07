import { describe, expect, it, vi } from 'vitest';
import {
  GlobalLivewireBrowserRuntime,
  type LivewireActionHandle,
  type LivewireActionInterceptorContext,
  type LivewireBrowserRuntime,
  type LivewireWire,
} from '../src/livewire-browser-runtime.js';
import { LivewireBindingExecutionError } from '../src/livewire-errors.js';

describe('Livewire browser compatibility boundary', () => {
  it('defines an exact find-only browser runtime port', () => {
    const wire: LivewireWire = {
      $id: 'component-1',
      $call: vi.fn(async () => ({ ok: true })),
    };
    const runtime: LivewireBrowserRuntime = {
      find(componentId) {
        return componentId === 'component-1' ? wire : undefined;
      },
    };

    expect(runtime.find('component-1')).toBe(wire);
    expect(runtime.find('other')).toBeUndefined();
  });

  it('models only the documented component-scoped action interceptor needed for cancellation', () => {
    const cancel = vi.fn();
    const action: LivewireActionHandle = { cancel };
    let capturedMethod: string | undefined;
    let capturedCallback: ((context: LivewireActionInterceptorContext) => void) | undefined;
    const unsubscribe = vi.fn();
    const wire: LivewireWire = {
      $id: 'component-1',
      $call: vi.fn(async () => ({ ok: true })),
      intercept(method, callback) {
        capturedMethod = method;
        capturedCallback = callback;
        return unsubscribe;
      },
    };

    const off = wire.intercept?.('addItem', ({ action: capturedAction, onSend }) => {
      onSend(() => capturedAction.cancel());
    });
    capturedCallback?.({
      action,
      onSend(callback) {
        callback();
      },
    });

    expect(capturedMethod).toBe('addItem');
    expect(cancel).toHaveBeenCalledTimes(1);
    expect(off).toBe(unsubscribe);
  });

  it('wraps the ambient Livewire.find API without alternate lookup behavior', () => {
    const wire: LivewireWire = {
      $id: 'component-1',
      $call: vi.fn(async () => null),
    };
    const find = vi.fn((id: string) => id === 'component-1' ? wire : undefined);
    const runtime = new GlobalLivewireBrowserRuntime({ Livewire: { find } });

    expect(runtime.find('component-1')).toBe(wire);
    expect(find).toHaveBeenCalledExactlyOnceWith('component-1');
  });

  it('fails explicitly when the Livewire global is unavailable', () => {
    const runtime = new GlobalLivewireBrowserRuntime({});

    try {
      runtime.find('component-1');
      throw new Error('expected runtime failure');
    } catch (error) {
      expect(error).toBeInstanceOf(LivewireBindingExecutionError);
      expect((error as LivewireBindingExecutionError).code).toBe('livewire_runtime_unavailable');
    }
  });

  it('fails explicitly when Livewire.find is not callable', () => {
    const runtime = new GlobalLivewireBrowserRuntime({ Livewire: { find: null } });

    expect(() => runtime.find('component-1')).toThrow(LivewireBindingExecutionError);
  });
});
