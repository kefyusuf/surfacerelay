import { readFileSync } from 'node:fs';
import { describe, expect, it } from 'vitest';
import {
  createHtmxBindingTarget,
  HtmxBindingDescriptorError,
  parseHtmxBindingTarget,
} from '../src/htmx-binding-descriptor.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-htmx-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: 'htmx-src-1',
      method: 'POST',
      path: '/prep-list/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    },
    expiresAt: null,
    ...overrides,
  };
}

function withTarget(overrides: Record<string, unknown>): RuntimeBinding {
  const base = binding();
  return {
    ...base,
    target: {
      ...base.target,
      ...overrides,
    },
  };
}

function definition(inputSchema: Record<string, unknown>): ActionDefinition {
  return {
    id: 'prep_list.add_item',
    version: 1,
    title: 'Add item',
    description: 'Add an item',
    inputSchema,
    scope: 'page_scoped',
    effect: 'reversible_write',
    risk: 'moderate',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
  };
}

function definitionFromUnknown(inputSchema: unknown): ActionDefinition {
  return definition(inputSchema as Record<string, unknown>);
}

function expectCode(fn: () => unknown, code: HtmxBindingDescriptorError['code']): void {
  try {
    fn();
    throw new Error('Expected HTMX descriptor failure.');
  } catch (error) {
    expect(error).toBeInstanceOf(HtmxBindingDescriptorError);
    expect((error as HtmxBindingDescriptorError).code).toBe(code);
  }
}

describe('parseHtmxBindingTarget', () => {
  it('accepts the exact HTMX page binding target', () => {
    expect(parseHtmxBindingTarget(binding())).toEqual({
      sourceId: 'htmx-src-1',
      method: 'POST',
      path: '/prep-list/items',
      inputNames: ['item', 'note'],
      requiredInputNames: ['item'],
    });
  });

  it('rejects another driver and another lifecycle', () => {
    expectCode(() => parseHtmxBindingTarget(binding({ driver: 'livewire' })), 'runtime_binding_invalid');
    expectCode(() => parseHtmxBindingTarget(binding({ lifecycle: 'component' })), 'runtime_binding_invalid');
  });

  it('rejects missing and extra target keys', () => {
    const missing = { ...binding(), target: { sourceId: 'htmx-src-1' } };
    expectCode(() => parseHtmxBindingTarget(missing), 'runtime_binding_invalid');

    const extra = {
      ...binding(),
      target: { ...binding().target, tenantId: 'tenant-a' },
    };
    expectCode(() => parseHtmxBindingTarget(extra), 'runtime_binding_invalid');
  });

  it.each([
    '',
    '-starts-with-dash',
    'contains space',
    'x'.repeat(241),
  ])('rejects invalid sourceId %j', (sourceId) => {
    expectCode(
      () => parseHtmxBindingTarget(withTarget({ sourceId })),
      'source_id_invalid',
    );
  });

  it.each(['get', 'HEAD', 'OPTIONS', 'TRACE', 'CUSTOM'])('rejects unsupported method %s', (method) => {
    expectCode(
      () => parseHtmxBindingTarget(withTarget({ method })),
      'method_invalid',
    );
  });

  it.each(['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as const)('accepts supported method %s', (method) => {
    expect(parseHtmxBindingTarget(withTarget({ method })).method).toBe(method);
  });

  it.each([
    '',
    'relative/path',
    '//example.com/path',
    'https://example.com/path',
    '/path#fragment',
    '/path\\child',
    '/path\nchild',
    `/${'x'.repeat(2048)}`,
  ])('rejects invalid path %j', (path) => {
    expectCode(
      () => parseHtmxBindingTarget(withTarget({ path })),
      'path_invalid',
    );
  });

  it.each([
    '/',
    '/orders/refund?view=table',
    `/${'x'.repeat(2047)}`,
  ])('accepts valid same-origin path %j', (path) => {
    expect(parseHtmxBindingTarget(withTarget({ path })).path).toBe(path);
  });

  it.each([
    { inputNames: 'item' },
    { requiredInputNames: 'item' },
    { inputNames: [''] },
    { requiredInputNames: [''] },
    { inputNames: ['item', 'item'] },
    { requiredInputNames: ['item', 'item'] },
    { inputNames: ['item', 42] },
    { requiredInputNames: ['item', 42] },
    { inputNames: ['item'], requiredInputNames: ['note'] },
  ])('rejects invalid input mapping %#', (overrides) => {
    expectCode(
      () => parseHtmxBindingTarget(withTarget(overrides)),
      'input_mapping_invalid',
    );
  });

  it('returns frozen mapping snapshots instead of caller-owned arrays', () => {
    const inputNames = ['item', 'note'];
    const requiredInputNames = ['item'];
    const parsed = parseHtmxBindingTarget(withTarget({ inputNames, requiredInputNames }));

    expect(Object.isFrozen(parsed)).toBe(true);
    expect(Object.isFrozen(parsed.inputNames)).toBe(true);
    expect(Object.isFrozen(parsed.requiredInputNames)).toBe(true);
    expect(parsed.inputNames).not.toBe(inputNames);
    expect(parsed.requiredInputNames).not.toBe(requiredInputNames);

    inputNames.push('later');
    requiredInputNames.push('note');

    expect(parsed.inputNames).toEqual(['item', 'note']);
    expect(parsed.requiredInputNames).toEqual(['item']);
  });
});

describe('createHtmxBindingTarget', () => {
  const options = { sourceId: 'htmx-src-1', method: 'POST' as const, path: '/prep-list/items' };

  it('derives deterministic named input mapping from a closed object schema', () => {
    const target = createHtmxBindingTarget(
      definition({
        type: 'object',
        properties: {
          note: { type: 'string' },
          item: { type: 'string' },
        },
        required: ['item'],
        additionalProperties: false,
      }),
      options,
    );

    expect(target.inputNames).toEqual(['item', 'note']);
    expect(target.requiredInputNames).toEqual(['item']);
  });

  it('supports a closed zero-input object schema', () => {
    const target = createHtmxBindingTarget(
      definition({ type: 'object', additionalProperties: false }),
      options,
    );

    expect(target.inputNames).toEqual([]);
    expect(target.requiredInputNames).toEqual([]);
  });

  it('keeps nested values under their top-level Action input name', () => {
    const target = createHtmxBindingTarget(
      definition({
        type: 'object',
        properties: {
          item: {
            type: 'object',
            properties: { label: { type: 'string' } },
          },
        },
        additionalProperties: false,
      }),
      options,
    );

    expect(target.inputNames).toEqual(['item']);
  });

  it('does not retain mutable Action schema mapping data', () => {
    const properties: Record<string, unknown> = {
      note: { type: 'string' },
      item: { type: 'string' },
    };
    const required = ['item'];
    const schema = {
      type: 'object',
      properties,
      required,
      additionalProperties: false,
    };

    const target = createHtmxBindingTarget(definition(schema), options);

    properties.attacker = { type: 'string' };
    required.push('note');

    expect(target.inputNames).toEqual(['item', 'note']);
    expect(target.requiredInputNames).toEqual(['item']);
    expect(Object.isFrozen(target)).toBe(true);
    expect(Object.isFrozen(target.inputNames)).toBe(true);
    expect(Object.isFrozen(target.requiredInputNames)).toBe(true);
  });

  it.each([
    ['null schema', null],
    ['array schema', []],
    ['primitive schema', 'string'],
    ['non-object type', { type: 'array', additionalProperties: false }],
    ['missing additionalProperties', { type: 'object' }],
    ['open additionalProperties', { type: 'object', additionalProperties: true }],
    ['array properties', { type: 'object', properties: [], additionalProperties: false }],
    ['primitive properties', { type: 'object', properties: 'item', additionalProperties: false }],
    ['primitive required', { type: 'object', required: 'item', additionalProperties: false }],
    ['object required', { type: 'object', required: { item: true }, additionalProperties: false }],
    ['duplicate required', {
      type: 'object',
      properties: { item: { type: 'string' } },
      required: ['item', 'item'],
      additionalProperties: false,
    }],
    ['empty required', {
      type: 'object',
      properties: { item: { type: 'string' } },
      required: [''],
      additionalProperties: false,
    }],
    ['required outside properties', {
      type: 'object',
      properties: { item: { type: 'string' } },
      required: ['missing'],
      additionalProperties: false,
    }],
    ['empty property name', {
      type: 'object',
      properties: { '': { type: 'string' } },
      additionalProperties: false,
    }],
    ['patternProperties', { type: 'object', additionalProperties: false, patternProperties: { '.*': {} } }],
    ['$ref', { type: 'object', additionalProperties: false, $ref: '#/$defs/input' }],
    ['$dynamicRef', { type: 'object', additionalProperties: false, $dynamicRef: '#input' }],
    ['allOf', { type: 'object', additionalProperties: false, allOf: [] }],
    ['anyOf', { type: 'object', additionalProperties: false, anyOf: [] }],
    ['oneOf', { type: 'object', additionalProperties: false, oneOf: [] }],
    ['not', { type: 'object', additionalProperties: false, not: {} }],
    ['if', { type: 'object', additionalProperties: false, if: {} }],
    ['then', { type: 'object', additionalProperties: false, then: {} }],
    ['else', { type: 'object', additionalProperties: false, else: {} }],
    ['unevaluatedProperties', { type: 'object', additionalProperties: false, unevaluatedProperties: false }],
    ['dependentSchemas', { type: 'object', additionalProperties: false, dependentSchemas: {} }],
  ] as const)('rejects unsupported top-level input schema: %s', (_label, inputSchema) => {
    expectCode(
      () => createHtmxBindingTarget(definitionFromUnknown(inputSchema), options),
      'input_schema_unsupported',
    );
  });
});

describe('T-601 scope boundary', () => {
  it('keeps the descriptor free of browser/HTMX execution dependencies', () => {
    const source = readFileSync(
      new URL('../src/htmx-binding-descriptor.ts', import.meta.url),
      'utf8',
    );

    expect(source).not.toContain("from 'htmx.org'");
    expect(source).not.toContain('htmx.ajax(');
    expect(source).not.toContain('document.');
    expect(source).not.toContain('window.');
    expect(source).not.toContain('fetch(');
    expect(source).not.toContain('@surfacerelay/laravel');
  });
});
