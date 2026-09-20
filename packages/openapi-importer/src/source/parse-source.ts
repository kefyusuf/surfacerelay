import type { ImportDiagnostic } from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';

export type SourceFormat = 'json' | 'yaml';

export interface ParseOpenApiSourceInput {
  format: SourceFormat;
  content: string;
}

export interface ParsedOpenApiSource {
  document: JsonObject | null;
  diagnostics: ImportDiagnostic[];
}

export function parseOpenApiSource(
  _input: ParseOpenApiSourceInput,
): ParsedOpenApiSource {
  throw new Error('Task 2 parser not implemented');
}
