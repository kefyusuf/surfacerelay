import type {
  ParameterEvidence,
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
import {
  LocalRefTraversalBudget,
  resolveLocalPointer,
} from '../refs/local-ref-resolver.js';

export interface EffectiveParameterResult {
  parameters: ParameterEvidence[];
  diagnostics: ImportDiagnostic[];
}

interface ParameterLevelResult {
  parameters: ParameterEvidence[];
  diagnostics: ImportDiagnostic[];
}

function encodePointerToken(value: string): string {
  return value.replace(/~/g, '~0').replace(/\//g, '~1');
}

function resolveParameterObject(
  root: JsonObject,
  value: JsonValue,
  budget: LocalRefTraversalBudget,
): { parameter: JsonObject | null; diagnostics: ImportDiagnostic[] } {
  if (!isJsonObject(value)) {
    return {
      parameter: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Parameter entries must be objects or same-document references.',
        ),
      ],
    };
  }

  const reference = value.$ref;
  if (reference === undefined) {
    return { parameter: value, diagnostics: [] };
  }

  if (typeof reference !== 'string') {
    return {
      parameter: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Parameter $ref must be a string.',
        ),
      ],
    };
  }

  const resolved = resolveLocalPointer(root, reference, budget.fork());
  if (resolved.value === null || resolved.diagnostics.length > 0) {
    return {
      parameter: null,
      diagnostics: resolved.diagnostics,
    };
  }

  if (!isJsonObject(resolved.value)) {
    return {
      parameter: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Referenced Parameter target must be an object.',
        ),
      ],
    };
  }

  return { parameter: resolved.value, diagnostics: [] };
}

function parseParameterLevel(
  root: JsonObject,
  raw: JsonValue | undefined,
  basePointer: string,
  budget: LocalRefTraversalBudget,
): ParameterLevelResult {
  if (raw === undefined) {
    return { parameters: [], diagnostics: [] };
  }

  if (!Array.isArray(raw)) {
    return {
      parameters: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'OpenAPI parameters must be an array.',
        ),
      ],
    };
  }

  const parameters: ParameterEvidence[] = [];
  const diagnostics: ImportDiagnostic[] = [];
  const seen = new Set<string>();

  raw.forEach((entry, index) => {
    const sourcePointer = `${basePointer}/parameters/${index}`;
    const resolved = resolveParameterObject(root, entry, budget);
    diagnostics.push(...resolved.diagnostics);

    if (resolved.parameter === null) {
      return;
    }

    const name = resolved.parameter.name;
    const location = resolved.parameter.in;

    if (typeof name !== 'string' || typeof location !== 'string') {
      diagnostics.push(
        blockingDiagnostic(
          'invalid_openapi_document',
          `Parameter at ${sourcePointer} requires string name and in fields.`,
        ),
      );
      return;
    }

    const identity = `${location}\u0000${name}`;
    if (seen.has(identity)) {
      diagnostics.push(
        blockingDiagnostic(
          'invalid_openapi_document',
          `Duplicate parameter identity (${name}, ${location}) at ${basePointer}.`,
        ),
      );
      return;
    }
    seen.add(identity);

    const schema = resolved.parameter.schema;
    const evidence: ParameterEvidence = {
      name,
      in: location,
      required: resolved.parameter.required === true,
      sourcePointer,
    };

    if (isJsonObject(schema)) {
      evidence.schema = schema;
    }

    parameters.push(evidence);
  });

  return { parameters, diagnostics };
}

export function extractEffectiveParameters(
  root: JsonObject,
  pathItem: JsonObject,
  operation: JsonObject,
  pathPointer: string,
  operationPointer: string,
  budget: LocalRefTraversalBudget = new LocalRefTraversalBudget(),
): EffectiveParameterResult {
  const pathLevel = parseParameterLevel(
    root,
    pathItem.parameters,
    pathPointer,
    budget,
  );
  const operationLevel = parseParameterLevel(
    root,
    operation.parameters,
    operationPointer,
    budget,
  );

  const effective = [...pathLevel.parameters];
  const indexes = new Map<string, number>();

  effective.forEach((parameter, index) => {
    indexes.set(`${parameter.in}\u0000${parameter.name}`, index);
  });

  for (const parameter of operationLevel.parameters) {
    const identity = `${parameter.in}\u0000${parameter.name}`;
    const existing = indexes.get(identity);

    if (existing === undefined) {
      indexes.set(identity, effective.length);
      effective.push(parameter);
      continue;
    }

    effective[existing] = parameter;
  }

  return {
    parameters: effective,
    diagnostics: [
      ...pathLevel.diagnostics,
      ...operationLevel.diagnostics,
    ],
  };
}
