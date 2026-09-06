import { describe, expect, it, vi } from 'vitest';
import {
  GlobalLivewireBrowserRuntime,
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
