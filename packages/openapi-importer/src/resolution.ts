import type { JsonObject } from './json-value.js';

export type ActionScope =
  | 'portable'
  | 'page_scoped'
  | 'browser_local'
  | 'headless';

export type ActionEffect =
  | 'read'
  | 'reversible_write'
  | 'destructive_write'
  | 'external_side_effect';

export type ActionRisk =
  | 'low'
  | 'moderate'
  | 'high'
  | 'consequential';

export type IdempotencyPolicy =
  | 'none'
  | 'recommended_key'
  | 'required_key';

export type OutputSensitivity =
  | 'normal'
  | 'sensitive';

export type OutputContentTrust =
  | 'trusted_application_data'
  | 'contains_untrusted_content';

export type ContextRequirement =
  | 'authenticated_actor'
  | 'tenant'
  | 'current_record'
  | 'current_selection'
  | 'browser_session'
  | 'human_confirmation';

export type SchemaResolution =
  | { kind: 'candidate_suggestion' }
  | { kind: 'explicit'; schema: JsonObject };

export type OutputSchemaResolution =
  | { kind: 'candidate_suggestion' }
  | { kind: 'explicit'; schema: JsonObject | null };

export interface ImportResolution {
  id: string;
  version: number;
  title: string;
  description: string;
  scope: ActionScope;
  effect: ActionEffect;
  risk: ActionRisk;
  idempotency: IdempotencyPolicy;
  outputSensitivity: OutputSensitivity;
  outputContentTrust: OutputContentTrust;
  contextRequirements: ContextRequirement[];
  inputSchema: SchemaResolution;
  outputSchema: OutputSchemaResolution;
}
