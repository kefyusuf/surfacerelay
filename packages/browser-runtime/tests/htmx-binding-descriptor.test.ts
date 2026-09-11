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
});
