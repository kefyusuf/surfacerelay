import { vi } from 'vitest';
import { LivewireBrowserDriver } from '../../src/livewire-browser-driver.js';
import type {
  LivewireBrowserRuntime,
  LivewireWire,
} from '../../src/livewire-browser-runtime.js';
import type { RuntimeBinding } from '../../src/types.js';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceAdapter,
} from './binding-driver-conformance-suite.js';

function livewireBinding(): RuntimeBinding {
  return {
    bindingId: 'conformance-livewire-1',
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

function makeWire(id: string): {
  wire: LivewireWire;
  call: ReturnType<typeof vi.fn>;
} {
  const call = vi.fn(async (_method: string, ..._params: unknown[]) => ({ ok: true }));
  return {
    wire: {
      $id: id,
      $call: call,
    },
    call,
  };
}

export const livewireConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'livewire',

  createHarness() {
    const original = makeWire('component-1');
    const replacement = makeWire('component-2');
    let resolved: LivewireWire | undefined = original.wire;

    const runtime: LivewireBrowserRuntime = {
      find: vi.fn(() => resolved),
    };

    const binding = livewireBinding();

    return {
      driver: new LivewireBrowserDriver(runtime, { now: () => CONFORMANCE_NOW }),
      binding,
      validInput: { name: 'passport' },
      invalidTargetBinding: {
        ...binding,
        target: {
          componentId: 'component-1',
          method: 'addItem',
          inputOrder: ['name'],
        },
      },
      unknownInput: { name: 'passport', extra: true },
      missingRequiredInput: {},

      makeTargetStale() {
        resolved = undefined;
      },

      replaceTargetWithEquivalentIdentity() {
        resolved = replacement.wire;
      },

      frameworkDispatchCount() {
        return original.call.mock.calls.length + replacement.call.mock.calls.length;
      },

      replacementDispatchCount() {
        return replacement.call.mock.calls.length;
      },
    };
  },
};
