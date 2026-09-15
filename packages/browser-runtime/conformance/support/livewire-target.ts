import { LivewireBrowserDriver } from '../../src/livewire-browser-driver.js';
import type {
  LivewireBrowserRuntime,
  LivewireWire,
} from '../../src/livewire-browser-runtime.js';
import type { RuntimeBinding } from '../../src/types.js';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceTarget,
} from './binding-driver-target.js';

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
  callCount(): number;
} {
  let calls = 0;
  return {
    wire: {
      $id: id,
      async $call(_method: string, ..._params: unknown[]): Promise<unknown> {
        calls += 1;
        return { ok: true };
      },
    },
    callCount() {
      return calls;
    },
  };
}

export function createLivewireConformanceTarget(
  now: Date = CONFORMANCE_NOW,
): BindingDriverConformanceTarget {
  const original = makeWire('component-1');
  const replacement = makeWire('component-2');
  let resolved: LivewireWire | undefined = original.wire;

  const runtime: LivewireBrowserRuntime = {
    find: () => resolved,
  };

  const binding = livewireBinding();

  return {
    driver: new LivewireBrowserDriver(runtime, { now: () => now }),
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
      return original.callCount() + replacement.callCount();
    },

    replacementDispatchCount() {
      return replacement.callCount();
    },
  };
}
