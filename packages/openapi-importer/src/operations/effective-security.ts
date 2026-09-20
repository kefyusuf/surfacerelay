import type { SecurityEvidence } from '../candidate.js';
import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';

export interface EffectiveSecurityResult {
  security: SecurityEvidence;
  diagnostics: ImportDiagnostic[];
}

export function extractEffectiveSecurity(
  _root: JsonObject,
  _operation: JsonObject,
): EffectiveSecurityResult {
  throw new Error('Task 4 effective security not implemented');
}
