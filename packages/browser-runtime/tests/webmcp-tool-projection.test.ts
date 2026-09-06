import { describe, expect, it, vi } from 'vitest';
import {
  projectBoundActionTool,
  projectWebMcpToolName,
  type BoundActionTool,
} from '../src/webmcp-tool-projection.js';
import { projectAnnotations } from '../src/webmcp-projection.js';
import type { ActionDefinition, RuntimeBinding } from '../src/types.js';

function definition(overrides: Partial<ActionDefinition> = {}): ActionDefinition {
  return {
    id: 'orders.get',
    version: 1,
    title: 'Get order',
    description: 'Gets an order',
    inputSchema: { type: 'object' },
    scope: 'portable',
    effect: 'read',
    risk: 'low',
    idempotency: 'none',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: [],
    ...overrides,
  };
}

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-1',
    action: { id: 'orders.get', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: { componentId: 'component-1', method: 'getOrder' },
    expiresAt: null,
    ...overrides,
  };
}

function candidate(
  definitionOverrides: Partial<ActionDefinition> = {},
  bindingOverrides: Partial<RuntimeBinding> = {},
): BoundActionTool {
  const def = definition(definitionOverrides);
  return {
    definition: def,
    binding: binding({
      action: { id: def.id, version: def.version },
      ...bindingOverrides,
    }),
  };
}

describe('WebMCP bound-action tool projection', () => {
  it('projects exact action identity to a versioned WebMCP tool name', () => {
    expect(projectWebMcpToolName(definition({ id: 'prep_list.add_item', version: 1 })))
      .toBe('prep_list.add_item.v1');
    expect(projectWebMcpToolName(definition({ id: 'orders.refund', version: 3 })))
      .toBe('orders.refund.v3');
  });

  it('rejects definition and binding identity mismatch', () => {
    const value = candidate(
      { id: 'orders.refund', version: 2 },
      { action: { id: 'orders.refund', version: 1 } },
    );

    expect(() => projectBoundActionTool(value, async () => null))
      .toThrow('Action definition and binding identity must match exactly.');
  });

  it('rejects projected tool names longer than the WebMCP 128-character limit', () => {
    const id = `${'a'.repeat(62)}.${'b'.repeat(62)}`;

    expect(() => projectWebMcpToolName(definition({ id, version: 1234 })))
      .toThrow('Projected WebMCP tool name is invalid.');
  });

  it('accepts a projected tool name exactly at the 128-character boundary', () => {
    const id = `${'a'.repeat(60)}.${'b'.repeat(61)}`;
    const name = projectWebMcpToolName(definition({ id, version: 1234 }));

    expect(name).toHaveLength(128);
  });

  it('projects stable definition metadata and T-302 annotations without reinterpretation', () => {
    const value = candidate({
      id: 'orders.refund',
      title: 'Refund order',
      description: 'Refunds one order',
      inputSchema: { type: 'object', required: ['orderId'] },
      effect: 'reversible_write',
      risk: 'consequential',
      outputSensitivity: 'sensitive',
      outputContentTrust: 'contains_untrusted_content',
    });

    const tool = projectBoundActionTool(value, async () => null);

    expect(tool).toMatchObject({
      name: 'orders.refund.v1',
      title: value.definition.title,
      description: value.definition.description,
      inputSchema: value.definition.inputSchema,
      annotations: projectAnnotations(value.definition),
    });
    expect(Object.keys(tool.annotations)).toEqual([
      'readOnlyHint',
      'untrustedContentHint',
      'consequentialHint',
    ]);
  });

  it('preserves execution input and per-execution signal unchanged', async () => {
    const execute = vi.fn(async () => ({ ok: true }));
    const value = candidate();
    const input = { orderId: 42 };
    const signal = new AbortController().signal;
    const tool = projectBoundActionTool(value, execute);

    await expect(tool.execute(input, { signal })).resolves.toEqual({ ok: true });
    expect(execute).toHaveBeenCalledTimes(1);
    expect(execute).toHaveBeenCalledWith(input, { signal });
  });

  it('does not derive tool identity from binding or target identity', () => {
    const first = candidate({}, {
      bindingId: 'binding-a',
      target: { componentId: 'component-a', method: 'getOrder' },
    });
    const second = candidate({}, {
      bindingId: 'binding-b',
      target: { componentId: 'component-b', method: 'getOrder' },
    });

    expect(projectBoundActionTool(first, async () => null).name)
      .toBe(projectBoundActionTool(second, async () => null).name);
  });
});
