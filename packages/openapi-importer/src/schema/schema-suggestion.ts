import type { OpenApiImportCandidate } from '../candidate.js';
import type { JsonObject } from '../json-value.js';

export function applySchemaSuggestions(
  _document: JsonObject,
  _candidate: OpenApiImportCandidate,
): OpenApiImportCandidate {
  throw new Error('Task 5 schema suggestions not implemented');
}
