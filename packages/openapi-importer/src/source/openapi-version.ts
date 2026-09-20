import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';

export type OpenApiFamily = '3.1' | '3.2';

export interface OpenApiFamilyResult {
  family: OpenApiFamily | null;
  diagnostics: ImportDiagnostic[];
}

const SUPPORTED_VERSION = /^3\.(1|2)\.(0|[1-9]\d*)$/;

export function parseOpenApiFamily(
  document: JsonObject,
): OpenApiFamilyResult {
  const version = document.openapi;

  if (typeof version !== 'string') {
    return {
      family: null,
      diagnostics: [
        blockingDiagnostic(
          'unsupported_openapi_version',
          'OpenAPI version must be an explicit supported 3.1.x or 3.2.x string.',
        ),
      ],
    };
  }

  const match = SUPPORTED_VERSION.exec(version);
  if (match === null) {
    return {
      family: null,
      diagnostics: [
        blockingDiagnostic(
          'unsupported_openapi_version',
          `OpenAPI version "${version}" is outside the supported 3.1.x/3.2.x families.`,
        ),
      ],
    };
  }

  return {
    family: match[1] === '1' ? '3.1' : '3.2',
    diagnostics: [],
  };
}
