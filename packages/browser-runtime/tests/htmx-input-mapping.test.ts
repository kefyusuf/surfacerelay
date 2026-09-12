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

function singleNameTarget(name: string): HtmxBindingTarget {
  return Object.freeze({
    sourceId: 'src-special',
    method: 'POST',
    path: '/items',
    inputNames: Object.freeze([name]),
    requiredInputNames: Object.freeze([name]),
  });
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

  it('preserves __proto__ as an exact own mapped Action input', () => {
    const specialTarget = singleNameTarget('__proto__');
    const input = Object.create(null) as Record<string, unknown>;
    Object.defineProperty(input, '__proto__', {
      enumerable: true,
      value: 'coffee',
    });

    const mapped = mapHtmxActionInput(specialTarget, input);

    expect(Object.prototype.hasOwnProperty.call(mapped, '__proto__')).toBe(true);
    expect(mapped.__proto__).toBe('coffee');
  });

  it('rejects hasOwnProperty as an HTMX 2.x object-values compatibility hazard', () => {
    const specialTarget = singleNameTarget('hasOwnProperty');
    const input = Object.create(null) as Record<string, unknown>;
    Object.defineProperty(input, 'hasOwnProperty', {
      enumerable: true,
      value: 'business-value',
    });

    try {
      mapHtmxActionInput(specialTarget, input);
      throw new Error('expected HTMX input mapping failure');
    } catch (error) {
      expect(error).toBeInstanceOf(HtmxBindingExecutionError);
      expect((error as HtmxBindingExecutionError).code).toBe('binding_input_unmappable');
    }
  });

  it('does not allow inherited Object.prototype.toJSON to replace validated object content', () => {
    const previous = Object.getOwnPropertyDescriptor(Object.prototype, 'toJSON');
    let mapped: Readonly<Record<string, string>> | undefined;

    Object.defineProperty(Object.prototype, 'toJSON', {
      configurable: true,
      writable: true,
      value: () => ({ injected: true }),
    });

    try {
      mapped = mapHtmxActionInput(target, { item: { safe: 'value' } });
    } finally {
      if (previous === undefined) {
        delete (Object.prototype as { toJSON?: unknown }).toJSON;
      } else {
        Object.defineProperty(Object.prototype, 'toJSON', previous);
      }
    }

    expect(mapped).toEqual({ item: '{"safe":"value"}' });
  });

  it('does not allow inherited Array.prototype.toJSON to replace validated array content', () => {
    const previous = Object.getOwnPropertyDescriptor(Array.prototype, 'toJSON');
    let mapped: Readonly<Record<string, string>> | undefined;

    Object.defineProperty(Array.prototype, 'toJSON', {
      configurable: true,
      writable: true,
      value: () => ['injected'],
    });

    try {
      mapped = mapHtmxActionInput(target, { item: ['safe'] });
    } finally {
      if (previous === undefined) {
        delete (Array.prototype as { toJSON?: unknown }).toJSON;
      } else {
        Object.defineProperty(Array.prototype, 'toJSON', previous);
      }
    }

    expect(mapped).toEqual({ item: '["safe"]' });
  });

  it('returns a frozen output snapshot', () => {
    const mapped = mapHtmxActionInput(target, { item: 'coffee' });
    expect(Object.isFrozen(mapped)).toBe(true);
  });
});
