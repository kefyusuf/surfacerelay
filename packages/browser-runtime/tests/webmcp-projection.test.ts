import { describe, expect, it } from 'vitest';
import { projectAnnotations } from '../src/webmcp-projection.js';
import type { ActionDefinition } from '../src/types.js';

const base: ActionDefinition = {
  id: 'orders.get',
  version: 1,
  title: 'Get order',
  description: 'Gets an order',
  inputSchema: {},
  scope: 'portable',
  effect: 'read',
  risk: 'low',
  idempotency: 'none',
  outputSensitivity: 'normal',
  outputContentTrust: 'trusted_application_data',
  contextRequirements: [],
};

function action(overrides: Partial<ActionDefinition>): ActionDefinition {
  return { ...base, ...overrides };
}

describe('WebMCP projection', () => {
  it('projects the three supported hints deterministically', () => {
    expect(projectAnnotations(base)).toEqual({
      readOnlyHint: true,
      untrustedContentHint: false,
      consequentialHint: false,
    });
  });

  it('does not mutate the ActionDefinition', () => {
    const definition = action({ risk: 'consequential' });
    const before = structuredClone(definition);

    projectAnnotations(definition);

    expect(definition).toEqual(before);
  });

  it.each([
    ['read', true],
    ['reversible_write', false],
    ['destructive_write', false],
    ['external_side_effect', false],
  ] as const)('maps effect=%s only to readOnlyHint=%s', (effect, expected) => {
    expect(projectAnnotations(action({ effect })).readOnlyHint).toBe(expected);
  });

  it('does not infer consequential risk from destructive writes', () => {
    expect(projectAnnotations(action({ effect: 'destructive_write', risk: 'low' }))).toEqual({
      readOnlyHint: false,
      untrustedContentHint: false,
      consequentialHint: false,
    });
  });

  it('does not infer consequential risk from external side effects', () => {
    expect(projectAnnotations(action({ effect: 'external_side_effect', risk: 'moderate' })).consequentialHint).toBe(false);
  });

  it('projects consequential risk independently from effect', () => {
    expect(projectAnnotations(action({ effect: 'reversible_write', risk: 'consequential' }))).toEqual({
      readOnlyHint: false,
      untrustedContentHint: false,
      consequentialHint: true,
    });
  });

  it('allows read-only and consequential to both be true', () => {
    expect(projectAnnotations(action({ effect: 'read', risk: 'consequential' }))).toEqual({
      readOnlyHint: true,
      untrustedContentHint: false,
      consequentialHint: true,
    });
  });

  it.each([
    ['trusted_application_data', 'normal', false],
    ['trusted_application_data', 'sensitive', false],
    ['contains_untrusted_content', 'normal', true],
    ['contains_untrusted_content', 'sensitive', true],
  ] as const)(
    'maps outputContentTrust=%s with sensitivity=%s to untrustedContentHint=%s',
    (outputContentTrust, outputSensitivity, expected) => {
      expect(projectAnnotations(action({ outputContentTrust, outputSensitivity })).untrustedContentHint).toBe(expected);
    },
  );

  it('does not project unsupported sensitivity/idempotency/effect-derived hints', () => {
    const annotations = projectAnnotations(action({
      effect: 'destructive_write',
      idempotency: 'required_key',
      outputSensitivity: 'sensitive',
    }));

    expect(Object.keys(annotations).sort()).toEqual([
      'consequentialHint',
      'readOnlyHint',
      'untrustedContentHint',
    ]);
    expect(annotations).not.toHaveProperty('sensitivityHint');
    expect(annotations).not.toHaveProperty('destructiveHint');
    expect(annotations).not.toHaveProperty('idempotentHint');
    expect(annotations).not.toHaveProperty('openWorldHint');
  });
});
