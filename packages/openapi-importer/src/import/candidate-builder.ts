import type { OpenApiImportCandidate } from '../candidate.js';
import type { ImportDiagnostic } from '../diagnostics.js';
import type { ParseOpenApiSourceInput } from '../source/parse-source.js';

export interface OpenApiImportReport {
  openapiVersion?: string;
  candidates: OpenApiImportCandidate[];
  diagnostics: ImportDiagnostic[];
  truncatedDiagnostics: boolean;
}

export function importOpenApi(
  _input: ParseOpenApiSourceInput,
): OpenApiImportReport {
  throw new Error('Task 6 candidate builder not implemented');
}
