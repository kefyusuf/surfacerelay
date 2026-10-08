import type { LivewireWire } from './livewire-browser-runtime.js';

export interface FilamentSelectionRuntime {
  sync(componentId: string, wire: LivewireWire): void;
}

interface SelectionElement {
  getAttribute(name: string): string | null;
  querySelectorAll(selector: string): Iterable<SelectionTable>;
}

interface SelectionTable {
  closest(selector: string): unknown;
}

interface SelectionWire extends LivewireWire {
  $el: SelectionElement;
  $set(name: string, value: unknown, live: false): void;
}

interface SelectionState {
  isTrackingDeselectedRecords: boolean;
  selectedRecords: Set<string>;
  deselectedRecords: Set<string>;
}

interface AmbientRoot {
  Alpine: { $data(table: SelectionTable): SelectionState };
}

const failureMessage = 'Filament table selection is unavailable or invalid.';

/** Synchronizes only the exact component already resolved by driver preflight. */
export class GlobalFilamentSelectionRuntime implements FilamentSelectionRuntime {
  constructor(private readonly root: unknown = globalThis) {}

  sync(componentId: string, wire: LivewireWire): void {
    try {
      const selectionWire = wire as SelectionWire;
      const component = selectionWire.$el;
      const set = selectionWire.$set;
      if (typeof component?.getAttribute !== 'function'
        || typeof component.querySelectorAll !== 'function' || typeof set !== 'function'
        || component.getAttribute('wire:id') !== componentId) {
        throw new Error(failureMessage);
      }
      const tables = [...component.querySelectorAll('[x-data^="filamentTable("]')]
        .filter((table) => {
          if (typeof table?.closest !== 'function') throw new Error(failureMessage);
          return table.closest('[wire\\:id]') === component;
        });
      if (tables.length !== 1) throw new Error(failureMessage);
      const alpine = (this.root as AmbientRoot)?.Alpine;
      const readState = alpine?.$data;
      if (typeof readState !== 'function') throw new Error(failureMessage);
      const state = readState.call(alpine, tables[0]);
      const tracking = state?.isTrackingDeselectedRecords;
      const selectedSet = state?.selectedRecords;
      const deselectedSet = state?.deselectedRecords;
      if (typeof tracking !== 'boolean'
        || !(selectedSet instanceof Set) || !(deselectedSet instanceof Set)) {
        throw new Error(failureMessage);
      }
      const selected = [...selectedSet];
      const deselected = [...deselectedSet];
      if ([...selected, ...deselected].some((key) => typeof key !== 'string')) {
        throw new Error(failureMessage);
      }
      set.call(wire, 'isTrackingDeselectedTableRecords', tracking, false);
      set.call(wire, 'selectedTableRecords', selected, false);
      set.call(wire, 'deselectedTableRecords', deselected, false);
    } catch {
      // Runtime objects may contain sensitive values or hostile exception objects.
      throw new Error(failureMessage);
    }
  }
}
