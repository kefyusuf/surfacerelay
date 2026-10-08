import { describe, expect, it, vi } from 'vitest';
import { GlobalFilamentSelectionRuntime } from '../src/filament-selection-runtime.js';

function fixture() {
  const writes: unknown[][] = [];
  const component = {
    getAttribute: (name: string) => name === 'wire:id' ? 'component-1' : null,
    querySelectorAll: (selector: string) => selector === '[x-data^="filamentTable("]' ? tables : [],
  };
  const table = { closest: (selector: string) => selector === '[wire\\:id]' ? component : null };
  const tables = [table];
  const state = {
    isTrackingDeselectedRecords: true,
    selectedRecords: new Set(['101', '102']),
    deselectedRecords: new Set(['103']),
  };
  const wire = {
    $id: 'component-1',
    $call: vi.fn(async () => undefined),
    $el: component,
    $set(name: string, value: unknown, live: boolean) { writes.push([name, value, live]); },
  };
  const find = vi.fn(() => { throw new Error('A second lookup must not occur.'); });
  const root = { Livewire: { find }, Alpine: { $data: (_table: unknown) => state } };
  return { writes, component, tables, table, state, wire, find, root };
}

describe('Filament selection runtime boundary', () => {
  it('defers the visible table selection on the exact prevalidated wire without another lookup', () => {
    const f = fixture();
    new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire);
    expect(f.writes).toEqual([
      ['isTrackingDeselectedTableRecords', true, false],
      ['selectedTableRecords', ['101', '102'], false],
      ['deselectedTableRecords', ['103'], false],
    ]);
    expect(f.find).not.toHaveBeenCalled();
    expect(f.wire.$call).not.toHaveBeenCalled();
  });

  it('rejects a component element owned by another wire before any writes', () => {
    const f = fixture();
    f.component.getAttribute = () => 'foreign-component';
    expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(f.writes).toEqual([]);
  });

  it('ignores a nested foreign table and synchronizes the one owned table', () => {
    const f = fixture();
    const nested = { closest: () => ({ foreign: true }) };
    f.tables.unshift(nested as typeof f.table);
    f.root.Alpine.$data = (table) => {
      if (table !== f.table) throw new Error('Foreign table state must never be inspected.');
      return f.state;
    };
    new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire);
    expect(f.writes).toEqual([
      ['isTrackingDeselectedTableRecords', true, false],
      ['selectedTableRecords', ['101', '102'], false],
      ['deselectedTableRecords', ['103'], false],
    ]);
  });

  it('rejects ambiguous owned tables before reading state or writing selection', () => {
    const f = fixture();
    f.tables.push({ closest: () => f.component });
    const readState = vi.fn(() => f.state);
    f.root.Alpine.$data = readState;
    expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(f.writes).toEqual([]);
    expect(readState).not.toHaveBeenCalled();
  });

  it.each([
    { isTrackingDeselectedRecords: 'true' },
    { selectedRecords: ['101'] },
    { deselectedRecords: ['103'] },
  ])('rejects malformed selection state with zero deferred writes: %j', (invalid) => {
    const f = fixture();
    Object.assign(f.state, invalid);
    expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(f.writes).toEqual([]);
  });

  it.each(['selectedRecords', 'deselectedRecords'] as const)(
    'rejects non-string %s keys without coercion or partial writes', (property) => {
      const f = fixture();
      const key = { toString: vi.fn(() => { throw new Error('PRIVATE_RECORD_KEY'); }) };
      Object.assign(f.state, { [property]: new Set([key]) });
      expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
        .toThrow('Filament table selection is unavailable or invalid.');
      expect(f.writes).toEqual([]);
      expect(key.toString).not.toHaveBeenCalled();
    },
  );

  it('uses one complete selection snapshot even when accessors and deferred writes mutate source state', () => {
    const f = fixture();
    for (const property of ['isTrackingDeselectedRecords', 'selectedRecords', 'deselectedRecords'] as const) {
      const value = f.state[property];
      let read = false;
      Object.defineProperty(f.state, property, { configurable: true, get() {
        if (read) throw new Error('Selection must not be reread after its snapshot.');
        read = true;
        return value;
      } });
    }
    const originalSet = f.wire.$set;
    f.wire.$set = (name, value, live) => {
      originalSet(name, value, live);
      if (name === 'isTrackingDeselectedTableRecords') {
        // The values passed to later writes must already be independent arrays.
        Object.defineProperty(f.state, 'selectedRecords', { value: new Set(['changed']) });
        Object.defineProperty(f.state, 'deselectedRecords', { value: new Set() });
      }
    };
    new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire);
    expect(f.writes).toEqual([
      ['isTrackingDeselectedTableRecords', true, false],
      ['selectedTableRecords', ['101', '102'], false],
      ['deselectedTableRecords', ['103'], false],
    ]);
  });

  it('stops after a partial write with a safe error and fully revalidates the next synchronization', () => {
    const f = fixture();
    let fail = true;
    f.wire.$set = (name, value, live) => {
      if (name === 'selectedTableRecords' && fail) throw new Error('PRIVATE_RECORD_KEY_AND_RUNTIME_ERROR');
      f.writes.push([name, value, live]);
    };
    const runtime = new GlobalFilamentSelectionRuntime(f.root);
    expect(() => runtime.sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(f.writes).toEqual([['isTrackingDeselectedTableRecords', true, false]]);
    fail = false;
    Object.assign(f.state, { deselectedRecords: ['invalid-array'] });
    expect(() => runtime.sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(f.writes).toEqual([['isTrackingDeselectedTableRecords', true, false]]);
    Object.assign(f.state, {
      isTrackingDeselectedRecords: false,
      selectedRecords: new Set(['201']),
      deselectedRecords: new Set(),
    });
    runtime.sync('component-1', f.wire);
    expect(f.writes.slice(1)).toEqual([
      ['isTrackingDeselectedTableRecords', false, false],
      ['selectedTableRecords', ['201'], false],
      ['deselectedTableRecords', [], false],
    ]);
    expect(f.wire.$call).not.toHaveBeenCalled();
  });

  it('keeps the validated component and callable adapters fixed through all deferred writes', () => {
    const f = fixture();
    let elementRead = false;
    Object.defineProperty(f.wire, '$el', { get() {
      if (elementRead) throw new Error('PRIVATE_CHANGED_COMPONENT');
      elementRead = true;
      return f.component;
    } });
    const set = f.wire.$set;
    let setterRead = false;
    Object.defineProperty(f.wire, '$set', { get() {
      if (setterRead) throw new Error('PRIVATE_CHANGED_SETTER');
      setterRead = true;
      return function (this: typeof f.wire, name: string, value: unknown, live: boolean) {
        expect(this).toBe(f.wire);
        set(name, value, live);
      };
    } });
    new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire);
    expect(f.writes).toEqual([
      ['isTrackingDeselectedTableRecords', true, false],
      ['selectedTableRecords', ['101', '102'], false],
      ['deselectedTableRecords', ['103'], false],
    ]);
  });

  it.each(['missing', 'foreign-only'])('rejects %s tables without reading Alpine state or writing', (kind) => {
    const f = fixture();
    f.tables.splice(0);
    if (kind === 'foreign-only') f.tables.push({ closest: () => null });
    const readState = vi.fn(() => f.state);
    f.root.Alpine.$data = readState;
    expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
      .toThrow('Filament table selection is unavailable or invalid.');
    expect(readState).not.toHaveBeenCalled();
    expect(f.writes).toEqual([]);
  });

  it.each(['element', 'identity-reader', 'table-query', 'setter', 'ownership-reader', 'alpine-reader'])(
    'rejects a missing or non-callable %s adapter before deferred writes', (capability) => {
      const f = fixture();
      switch (capability) {
        case 'element': Object.assign(f.wire, { $el: null }); break;
        case 'identity-reader': Object.assign(f.component, { getAttribute: null }); break;
        case 'table-query': Object.assign(f.component, { querySelectorAll: null }); break;
        case 'setter': Object.assign(f.wire, { $set: null }); break;
        case 'ownership-reader': Object.assign(f.table, { closest: null }); break;
        case 'alpine-reader': Object.assign(f.root.Alpine, { $data: null }); break;
      }
      expect(() => new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire))
        .toThrow('Filament table selection is unavailable or invalid.');
      expect(f.writes).toEqual([]);
      expect(f.wire.$call).not.toHaveBeenCalled();
    },
  );

  it('uses the default ambient Alpine adapter and preserves empty selection Sets', () => {
    const f = fixture();
    Object.assign(f.state, { isTrackingDeselectedRecords: false, selectedRecords: new Set(), deselectedRecords: new Set() });
    vi.stubGlobal('Alpine', f.root.Alpine);
    try {
      new GlobalFilamentSelectionRuntime().sync('component-1', f.wire);
      expect(f.writes).toEqual([
        ['isTrackingDeselectedTableRecords', false, false],
        ['selectedTableRecords', [], false],
        ['deselectedTableRecords', [], false],
      ]);
    } finally { vi.unstubAllGlobals(); }
  });

  it('does not expose or inspect a hostile exception from DOM or Alpine state', () => {
    const f = fixture();
    const inspect = vi.fn(() => { throw new Error('PRIVATE_EXCEPTION_PROPERTY'); });
    const hostile = { get message() { return inspect(); }, toString: inspect };
    f.root.Alpine.$data = () => { throw hostile; };
    let error: unknown;
    try { new GlobalFilamentSelectionRuntime(f.root).sync('component-1', f.wire); }
    catch (caught) { error = caught; }
    expect(error).toBeInstanceOf(Error);
    expect((error as Error).message).toBe('Filament table selection is unavailable or invalid.');
    expect(Object.hasOwn(error as object, 'cause')).toBe(false);
    expect(inspect).not.toHaveBeenCalled();
    expect(f.writes).toEqual([]);
  });
});
