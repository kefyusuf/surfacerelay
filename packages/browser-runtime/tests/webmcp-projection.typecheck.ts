import { projectAnnotations } from '../src/webmcp-projection.js';
import type { ActionDefinition } from '../src/types.js';

const definition: ActionDefinition = {
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

const exact: {
  readOnlyHint: boolean;
  untrustedContentHint: boolean;
  consequentialHint: boolean;
} = projectAnnotations(definition);

void exact;
