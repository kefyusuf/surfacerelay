import type { OperationSelectionResult } from '../candidate.js';
import type { JsonObject } from '../json-value.js';
import type { OpenApiFamily } from '../source/openapi-version.js';

export function selectRootPathOperations(
  _document: JsonObject,
  _family: OpenApiFamily,
): OperationSelectionResult {
  throw new Error('Task 4 operation selector not implemented');
}
