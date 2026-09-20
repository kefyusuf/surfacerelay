import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';

export interface SchemaSubsetResult {
  schema: JsonObject | null;
  diagnostics: ImportDiagnostic[];
}

export function copySupportedSchema(
  _document: JsonObject,
  _schema: JsonObject,
): SchemaSubsetResult {
  throw new Error('Task 5 schema subset not implemented');
}
