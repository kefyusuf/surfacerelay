import type { BindingDriver, DriverExecutionContext, RuntimeBinding } from './types.js';
import type { LivewireBrowserRuntime } from './livewire-browser-runtime.js';
import type { BoundActionTool } from './webmcp-tool-projection.js';
import { systemBrowserClock, type BrowserClock } from './runtime-binding-expiry.js';
import { executeLivewireBinding } from './livewire-execution.js';
import type { FilamentSelectionRuntime } from './filament-selection-runtime.js';

// Own plain descriptor data without JSON's undefined/NaN normalization or getters.
function snapshot(value: unknown, ancestors = new Set<object>()): unknown {
  if (value === null || value === undefined || typeof value === 'string' || typeof value === 'boolean') return value;
  if (typeof value === 'number' && Number.isFinite(value)) return value;
  if (typeof value !== 'object' || ancestors.has(value)) throw new Error('Filament binding descriptor is invalid.');
  const array = Array.isArray(value);
  if (!array && Object.getPrototypeOf(value) !== Object.prototype && Object.getPrototypeOf(value) !== null) {
    throw new Error('Filament binding descriptor is invalid.');
  }
  ancestors.add(value);
  const owned: Record<string, unknown> | unknown[] = array ? [] : Object.create(null);
  for (const key of Reflect.ownKeys(value)) {
    if (array && key === 'length') continue;
    const field = Object.getOwnPropertyDescriptor(value, key)!;
    if (typeof key !== 'string' || !('value' in field) || !field.enumerable) {
      throw new Error('Filament binding descriptor is invalid.');
    }
    Object.defineProperty(owned, key, { value: snapshot(field.value, ancestors), enumerable: true, writable: true, configurable: true });
  }
  if (array) (owned as unknown[]).length = (value as unknown[]).length;
  ancestors.delete(value);
  return owned;
}

function sameDescriptor(left: unknown, right: unknown): boolean {
  if (Object.is(left, right)) return true;
  if (typeof left !== 'object' || left === null || typeof right !== 'object' || right === null) return false;
  if (Array.isArray(left) !== Array.isArray(right)) return false;
  if (Array.isArray(left) && left.length !== (right as unknown[]).length) return false;
  const keys = Object.keys(left);
  return keys.length === Object.keys(right).length && keys.every(key =>
    Object.prototype.hasOwnProperty.call(right, key)
    && sameDescriptor((left as Record<string, unknown>)[key], (right as Record<string, unknown>)[key]));
}

export class FilamentSelectionCoordinator {
  private readonly pending = new Set<string>();

  acquire(componentId: string): () => void {
    if (this.pending.has(componentId)) throw new Error('A Filament invocation is already pending for this component.');
    this.pending.add(componentId);
    let released = false;
    return () => {
      if (released) return;
      released = true;
      this.pending.delete(componentId);
    };
  }
}

export interface FilamentBrowserDriverOptions {
  tools: readonly BoundActionTool[];
  selectionRuntime: FilamentSelectionRuntime;
  coordinator: FilamentSelectionCoordinator;
}

export class FilamentBrowserDriver implements BindingDriver {
  private readonly registrations = new Map<RuntimeBinding, { selection: boolean; binding: RuntimeBinding }>();
  private readonly selectionRuntime: FilamentBrowserDriverOptions['selectionRuntime'];
  private readonly coordinator: FilamentSelectionCoordinator;

  constructor(
    private readonly livewire: LivewireBrowserRuntime,
    options: FilamentBrowserDriverOptions,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {
    this.selectionRuntime = options.selectionRuntime;
    this.coordinator = options.coordinator;
    for (const tool of options.tools) {
      const selection = tool.definition.contextRequirements.includes('current_selection');
      const previous = this.registrations.get(tool.binding);
      if (previous !== undefined && previous.selection !== selection) {
        throw new Error('Filament binding has conflicting exposure policies.');
      }
      if (tool.definition.id !== tool.binding.action.id || tool.definition.version !== tool.binding.action.version) {
        throw new Error('Filament action definition and binding identity must match.');
      }
      this.registrations.set(tool.binding, {
        selection,
        binding: snapshot(tool.binding) as RuntimeBinding,
      });
    }
  }

  async execute(binding: RuntimeBinding, input: Record<string, unknown>, context: DriverExecutionContext): Promise<unknown> {
    const registration = this.registrations.get(binding);
    if (registration === undefined) throw new Error('Filament binding is not registered.');
    let unchanged = false;
    try { unchanged = sameDescriptor(snapshot(binding), registration.binding); } catch { /* Fail closed. */ }
    if (!unchanged) throw new Error('Filament binding descriptor changed after registration.');
    const componentId = registration.binding.target.componentId as string;
    const release = this.coordinator.acquire(componentId);
    let initiated = false;
    try {
      return await executeLivewireBinding(this.livewire, this.clock, registration.binding, input, context, {
        beforeDispatch: (id, wire) => {
          if (registration.selection) this.selectionRuntime.sync(id, wire);
        },
        onCall: promise => {
          initiated = true;
          void promise.then(release, release);
        },
      });
    } finally {
      if (!initiated) release();
    }
  }
}
