import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';

export type OpenApiFamily = '3.1' | '3.2';

export interface OpenApiFamilyResult {
  family: OpenApiFamily | null;
  diagnostics: ImportDiagnostic[];
}

export function parseOpenApiFamily(
  _document: JsonObject,
): OpenApiFamilyResult {
  throw new Error('Task 3 OpenAPI family parser not implemented');
}
