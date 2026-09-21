import type {
  SecurityEvidence,
  SecurityRequirementEvidence,
  SecuritySchemeEvidence,
} from '../candidate.js';
import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import {
  isJsonObject,
  type JsonObject,
  type JsonValue,
} from '../json-value.js';

export interface EffectiveSecurityResult {
  security: SecurityEvidence;
  diagnostics: ImportDiagnostic[];
}

function hasOwn(object: JsonObject, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(object, key);
}

function parseRequirements(
  value: JsonValue,
): { requirements: SecurityRequirementEvidence[]; diagnostics: ImportDiagnostic[] } {
  if (!Array.isArray(value)) {
    return {
      requirements: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'OpenAPI security must be an array of Security Requirement Objects.',
        ),
      ],
    };
  }

  const requirements: SecurityRequirementEvidence[] = [];
  const diagnostics: ImportDiagnostic[] = [];

  for (const requirement of value) {
    if (!isJsonObject(requirement)) {
      diagnostics.push(
        blockingDiagnostic(
          'invalid_openapi_document',
          'Security Requirement entries must be objects.',
        ),
      );
      continue;
    }

    const names = Object.keys(requirement).sort();
    if (names.length === 0) {
      requirements.push({ schemes: [], anonymous: true });
      continue;
    }

    const schemes: SecuritySchemeEvidence[] = [];
    let valid = true;

    for (const name of names) {
      const rawScopes = requirement[name];
      if (
        !Array.isArray(rawScopes) ||
        !rawScopes.every((scope) => typeof scope === 'string')
      ) {
        diagnostics.push(
          blockingDiagnostic(
            'invalid_openapi_document',
            `Security Requirement "${name}" must contain an array of strings.`,
          ),
        );
        valid = false;
        break;
      }

      schemes.push({
        scheme: name,
        scopes: [...rawScopes] as string[],
      });
    }

    if (valid) {
      requirements.push({ schemes, anonymous: false });
    }
  }

  return { requirements, diagnostics };
}

export function extractEffectiveSecurity(
  root: JsonObject,
  operation: JsonObject,
): EffectiveSecurityResult {
  const rootDeclared = hasOwn(root, 'security');
  const operationDeclared = hasOwn(operation, 'security');

  if (!rootDeclared && !operationDeclared) {
    return {
      security: {
        source: 'none',
        requirements: [],
        anonymousAlternative: false,
        inheritedSecurityRemoved: false,
      },
      diagnostics: [],
    };
  }

  const source = operationDeclared ? 'operation_override' : 'inherited';
  const raw = operationDeclared ? operation.security : root.security;

  if (raw === undefined) {
    return {
      security: {
        source,
        requirements: [],
        anonymousAlternative: false,
        inheritedSecurityRemoved: false,
      },
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Declared OpenAPI security must not be undefined.',
        ),
      ],
    };
  }

  const parsed = parseRequirements(raw);
  const emptyOverride =
    operationDeclared && Array.isArray(raw) && raw.length === 0;

  return {
    security: {
      source,
      requirements: parsed.requirements,
      anonymousAlternative: parsed.requirements.some(
        (requirement) => requirement.anonymous,
      ),
      inheritedSecurityRemoved: emptyOverride && rootDeclared,
    },
    diagnostics: parsed.diagnostics,
  };
}
