import { describe, expect, it } from 'vitest';
import { projectAnnotations } from '../src/webmcp-projection.js';
import type { ActionDefinition } from '../src/types.js';

const base: ActionDefinition = {
  id: 'orders.get', version: 1, title: 'Get order', description: 'Gets an order', inputSchema: {},
  scope: 'portable', effect: 'read', risk: 'low', idempotency: 'none',
  outputTrust: 'trusted_application_data', contextRequirements: [],
};

describe('WebMCP projection', () => {
  it('projects core semantics without changing the definition', () => {
    expect(projectAnnotations(base)).toEqual({ readOnlyHint: true, untrustedContentHint: false, consequentialHint: false });
    expect(base.effect).toBe('read');
  });
});
