import { describe, expect, it } from 'vitest';
import type { HtmxBindingTarget } from '../src/htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from '../src/htmx-errors.js';
import { mapHtmxActionInput } from '../src/htmx-input-mapping.js';

const target: HtmxBindingTarget = Object.freeze({
  sourceId: 'src-1',
  method: 'POST',
  path: '/items',
  inputNames: Object.freeze([
    'item',
    'count',
    'enabled',
    'nothing',
    'tags',
    'filters',
    'optional',
  ]),
  requiredInputNames: Object.freeze(['item']),
});

function expectUnmappable(input: Record<string, unknown>): void {
  try {
    mapHtmxActionInput(target, input);
    throw new Error('expected HTMX input mapping failure');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingExecutionError);
    expect((error as HtmxBindingExecutionError).code).toBe('binding_input_unmappable');
  }
}

describe('mapHtmxActionInput', () => {
  it('maps supported JSON-data values into deterministic top-level strings', () => {
    expect(mapHtmxActionInput(target, {
      item: 'coffee',
      count: 2.5,
      enabled: false,
      nothing: null,
      tags: ['hot', 'drink'],
      filters: { status: ['open', 'paid'] },
    })).toEqual({
      item: 'coffee',
      count: '2.5',
      enabled: 'false',
      nothing: 'null',
      tags: '["hot","drink"]',
      filters: '{"status":["open","paid"]}',
    });
  });

  it('allows omitted optional names independently', () => {
    expect(mapHtmxActionInput(target, { item: 'coffee' })).toEqual({ item: 'coffee' });
  });

  it('rejects a missing required own property, including inherited values', () => {
    expectUnmappable({});

    const inherited = Object.create({ item: 'coffee' }) as Record<string, unknown>;
    expectUnmappable(inherited);
  });

  it('rejects unknown own input names', () => {
    expectUnmappable({ item: 'coffee', unexpected: true });
  });

  it('rejects symbol top-level keys', () => {
    const input = { item: 'coffee' } as Record<PropertyKey, unknown>;
    input[Symbol('hidden')] = true;
    expectUnmappable(input as Record<string, unknown>);
  });

  it.each([
    undefined,
    Number.NaN,
    Number.POSITIVE_INFINITY,
    Number.NEGATIVE_INFINITY,
    1n,
    Symbol('x'),
    () => 'x',
    new Date('2026-09-12T00:00:00Z'),
    new Map([['x', 1]]),
    new Set(['x']),
    new Blob(['x']),
  ])('rejects unsupported scalar/object Action value %#', (value) => {
    expectUnmappable({ item: value });
  });

  it('rejects custom class instances', () => {
    class CustomValue {
      value = 'x';
    }

    expectUnmappable({ item: new CustomValue() });
  });

  it('rejects top-level and nested accessor properties', () => {
    const topLevel: Record<string, unknown> = {};
    Object.defineProperty(topLevel, 'item', {
      enumerable: true,
      get: () => 'computed',
    });
    expectUnmappable(topLevel);

    const nested: Record<string, unknown> = {};
    Object.defineProperty(nested, 'value', {
      enumerable: true,
      get: () => 'computed',
    });
    expectUnmappable({ item: nested });
  });

  it('rejects sparse arrays and arrays with extra own properties', () => {
    const sparse = new Array(2);
    sparse[1] = 'x';
    expectUnmappable({ item: sparse });

    const extra = ['x'] as unknown as string[] & Record<string, unknown>;
    extra.extra = true;
    expectUnmappable({ item: extra });
  });

  it('rejects object and array cycles', () => {
    const objectCycle: Record<string, unknown> = {};
    objectCycle.self = objectCycle;
    expectUnmappable({ item: objectCycle });

    const arrayCycle: unknown[] = [];
    arrayCycle.push(arrayCycle);
    expectUnmappable({ item: arrayCycle });
  });

  it('allows repeated shared references when they are not cyclic', () => {
    const shared = { value: 'same' };
    expect(mapHtmxActionInput(target, {
      item: { left: shared, right: shared },
    })).toEqual({
      item: '{"left":{"value":"same"},"right":{"value":"same"}}',
    });
  });

  it('rejects nested undefined and non-finite numbers', () => {
    expectUnmappable({ item: { nested: undefined } });
    expectUnmappable({ item: { nested: Number.NaN } });
    expectUnmappable({ item: [Number.POSITIVE_INFINITY] });
  });

  it('rejects non-enumerable and symbol nested object properties', () => {
    const nonEnumerable: Record<string, unknown> = {};
    Object.defineProperty(nonEnumerable, 'hidden', {
      value: 'x',
      enumerable: false,
    });
    expectUnmappable({ item: nonEnumerable });

    const withSymbol = { visible: 'x' } as Record<PropertyKey, unknown>;
    withSymbol[Symbol('hidden')] = 'y';
    expectUnmappable({ item: withSymbol });
  });

  it('returns a frozen output snapshot', () => {
    const mapped = mapHtmxActionInput(target, { item: 'coffee' });
    expect(Object.isFrozen(mapped)).toBe(true);
  });
});
