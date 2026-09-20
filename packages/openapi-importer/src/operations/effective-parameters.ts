import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';
import type { ParameterEvidence } from '../candidate.js';

export interface EffectiveParameterResult {
  parameters: ParameterEvidence[];
  diagnostics: ImportDiagnostic[];
}

export function extractEffectiveParameters(
  _root: JsonObject,
  _pathItem: JsonObject,
  _operation: JsonObject,
  _pathPointer: string,
  _operationPointer: string,
): EffectiveParameterResult {
  throw new Error('Task 4 effective parameters not implemented');
}
