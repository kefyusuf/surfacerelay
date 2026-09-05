import { describe, expect, it } from 'vitest';
import { projectAnnotations } from '../src/webmcp-projection.js';
import type { ActionDefinition } from '../src/types.js';

const base: ActionDefinition = {
  id: 'orders.get', version: 1, title: 'Get order', description: 'Gets an order', inputSchema: {},
  scope: 'portable', effect: 'read', risk: 'low', idempotency: 'none',
  outputSensitivity: 'normal', outputContentTrust: 'trusted_application_data', contextRequirements: [],
};

describe('WebMCP projection', () => {
  it('projects core semantics without changing the definition', () => {
    expect(projectAnnotations(base)).toEqual({ readOnlyHint: true, untrustedContentHint: false, consequentialHint: false });
    expect(base.effect).toBe('read');
  });

  it('keeps the untrusted-content hint when output is also sensitive (D-032)', () => {
    const sensitiveUntrusted: ActionDefinition = {
      ...base,
      outputSensitivity: 'sensitive',
      outputContentTrust: 'contains_untrusted_content',
    };

    const annotations = projectAnnotations(sensitiveUntrusted);

    expect(annotations.untrustedContentHint).toBe(true);
    // Sensitivity never suppresses the untrusted-content signal.
    expect(projectAnnotations({ ...sensitiveUntrusted, outputSensitivity: 'normal' }).untrustedContentHint).toBe(true);
  });

  it('does not project a sensitivity annotation (output policy owns redaction)', () => {
    const annotations = projectAnnotations({ ...base, outputSensitivity: 'sensitive' });
    expect(Object.keys(annotations)).not.toContain('sensitivityHint');
  });
});
