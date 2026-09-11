import { describe, expect, it } from 'vitest';
import {
  HtmxBindingDescriptorError,
  parseHtmxBindingTarget,
} from '../src/htmx-binding-descriptor.js';
import type { RuntimeBinding } from '../src/types.js';

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
