import type {
  MediaTypeEvidence,
  OpenApiImportCandidate,
  OperationSelectionResult,
  RequestBodyEvidence,
  ResponseEvidence,
} from '../candidate.js';
import {
  blockingDiagnostic,
  type ImportDiagnostic,
  warningDiagnostic,
} from '../diagnostics.js';
import {
  isJsonObject,
  type JsonObject,
  type JsonValue,
} from '../json-value.js';
import {
  MAX_OPERATIONS,
  MAX_SOURCE_TEXT_CHARS,
} from '../limits.js';
import {
  LocalRefTraversalBudget,
  resolveLocalPointer,
} from '../refs/local-ref-resolver.js';
import type { OpenApiFamily } from '../source/openapi-version.js';
import { extractEffectiveParameters } from './effective-parameters.js';
import { extractEffectiveSecurity } from './effective-security.js';

const METHODS_31 = [
  'get',
  'put',
  'post',
  'delete',
  'options',
  'head',
  'patch',
  'trace',
] as const;

const METHODS_32 = [...METHODS_31, 'query'] as const;

function encodePointerToken(value: string): string {
  return value.replace(/~/g, '~0').replace(/\//g, '~1');
}

function hasOwn(object: JsonObject, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(object, key);
}

function resolveReferencedObject(
  root: JsonObject,
  value: JsonValue,
  budget: LocalRefTraversalBudget,
  context: string,
): { value: JsonObject | null; diagnostics: ImportDiagnostic[] } {
  if (!isJsonObject(value)) {
    return {
      value: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          `${context} must be an object.`,
        ),
      ],
    };
  }

  let current = value;
  const localBudget = budget.fork();

  while (hasOwn(current, '$ref')) {
    const reference = current.$ref;
    if (typeof reference !== 'string') {
      return {
        value: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_openapi_document',
            `${context} $ref must be a string.`,
          ),
        ],
      };
    }

    const resolved = resolveLocalPointer(root, reference, localBudget);
    if (resolved.pointer === null) {
      return { value: null, diagnostics: resolved.diagnostics };
    }

    if (!isJsonObject(resolved.value)) {
      return {
        value: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_openapi_document',
            `${context} reference target must be an object.`,
          ),
        ],
      };
    }

    current = resolved.value;
  }

  return { value: current, diagnostics: [] };
}

function resolvePathItem(
  root: JsonObject,
  raw: JsonValue,
  budget: LocalRefTraversalBudget,
): { value: JsonObject | null; diagnostics: ImportDiagnostic[] } {
  if (!isJsonObject(raw)) {
    return {
      value: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Path Item must be an object.',
        ),
      ],
    };
  }

  if (!hasOwn(raw, '$ref')) {
    return { value: raw, diagnostics: [] };
  }

  if (Object.keys(raw).length !== 1) {
    return {
      value: null,
      diagnostics: [
        blockingDiagnostic(
          'path_item_ref_sibling_ambiguous',
          'Path Item $ref with sibling fields is unsupported in v1.',
        ),
      ],
    };
  }

  let current = raw;
  const localBudget = budget.fork();

  while (hasOwn(current, '$ref')) {
    if (Object.keys(current).length !== 1 || typeof current.$ref !== 'string') {
      return {
        value: null,
        diagnostics: [
          blockingDiagnostic(
            'path_item_ref_sibling_ambiguous',
            'Referenced Path Item must use $ref without sibling fields.',
          ),
        ],
      };
    }

    const resolved = resolveLocalPointer(root, current.$ref, localBudget);
    if (resolved.pointer === null) {
      return { value: null, diagnostics: resolved.diagnostics };
    }

    if (!isJsonObject(resolved.value)) {
      return {
        value: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_openapi_document',
            'Referenced Path Item target must be an object.',
          ),
        ],
      };
    }

    current = resolved.value;
  }

  return { value: current, diagnostics: [] };
}

interface MediaTypeExtractionResult {
  evidence: MediaTypeEvidence[];
  diagnostics: ImportDiagnostic[];
}

function extractMediaTypes(
  rawContent: JsonValue | undefined,
  basePointer: string,
): MediaTypeExtractionResult {
  if (rawContent === undefined) {
    return { evidence: [], diagnostics: [] };
  }

  if (!isJsonObject(rawContent)) {
    return {
      evidence: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          `Content at ${basePointer}/content must be an object when present.`,
        ),
      ],
    };
  }

  const evidence: MediaTypeEvidence[] = [];
  const diagnostics: ImportDiagnostic[] = [];

  for (const mediaType of Object.keys(rawContent).sort()) {
    const mediaPointer =
      `${basePointer}/content/${encodePointerToken(mediaType)}`;
    const mediaObject = rawContent[mediaType];

    if (!isJsonObject(mediaObject)) {
      diagnostics.push(
        blockingDiagnostic(
          'invalid_openapi_document',
          `Media Type Object at ${mediaPointer} must be an object.`,
        ),
      );
      continue;
    }

    const item: MediaTypeEvidence = {
      mediaType,
      sourcePointer: mediaPointer,
    };

    if (mediaObject.schema !== undefined) {
      if (!isJsonObject(mediaObject.schema)) {
        diagnostics.push(
          blockingDiagnostic(
            'invalid_openapi_document',
            `Schema at ${mediaPointer}/schema must be an object when present.`,
          ),
        );
      } else {
        item.schema = mediaObject.schema;
      }
    }

    evidence.push(item);
  }

  return { evidence, diagnostics };
}

function extractRequestBodies(
  root: JsonObject,
  operation: JsonObject,
  operationPointer: string,
  budget: LocalRefTraversalBudget,
): { evidence: RequestBodyEvidence[]; diagnostics: ImportDiagnostic[] } {
  const raw = operation.requestBody;
  if (raw === undefined) {
    return { evidence: [], diagnostics: [] };
  }

  const resolved = resolveReferencedObject(
    root,
    raw,
    budget,
    'Request Body',
  );
  if (resolved.value === null) {
    return { evidence: [], diagnostics: resolved.diagnostics };
  }

  const pointer = `${operationPointer}/requestBody`;
  const contentEvidence = extractMediaTypes(
    resolved.value.content,
    pointer,
  );

  return {
    evidence: [{
      required: resolved.value.required === true,
      content: contentEvidence.evidence,
      sourcePointer: pointer,
    }],
    diagnostics: [
      ...resolved.diagnostics,
      ...contentEvidence.diagnostics,
    ],
  };
}

function extractResponses(
  root: JsonObject,
  operation: JsonObject,
  operationPointer: string,
  budget: LocalRefTraversalBudget,
): { evidence: ResponseEvidence[]; diagnostics: ImportDiagnostic[] } {
  const raw = operation.responses;
  if (!isJsonObject(raw)) {
    return {
      evidence: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          `Operation at ${operationPointer} requires a responses object.`,
        ),
      ],
    };
  }

  const evidence: ResponseEvidence[] = [];
  const diagnostics: ImportDiagnostic[] = [];

  for (const statusCode of Object.keys(raw).sort()) {
    const responsePointer =
      `${operationPointer}/responses/${encodePointerToken(statusCode)}`;
    const response = resolveReferencedObject(
      root,
      raw[statusCode] as JsonValue,
      budget,
      'Response',
    );
    diagnostics.push(...response.diagnostics);

    if (response.value === null) {
      continue;
    }

    const contentEvidence = extractMediaTypes(
      response.value.content,
      responsePointer,
    );
    diagnostics.push(...contentEvidence.diagnostics);

    evidence.push({
      statusCode,
      content: contentEvidence.evidence,
      sourcePointer: responsePointer,
    });
  }

  return { evidence, diagnostics };
}

function boundedSourceText(
  operation: JsonObject,
  field: 'summary' | 'description',
  diagnostics: ImportDiagnostic[],
): string | undefined {
  const value = operation[field];

  if (value === undefined) {
    return undefined;
  }

  if (typeof value !== 'string') {
    diagnostics.push(
      blockingDiagnostic(
        'invalid_openapi_document',
        `Operation ${field} must be a string when present.`,
      ),
    );
    return undefined;
  }

  if ([...value].length > MAX_SOURCE_TEXT_CHARS) {
    diagnostics.push(
      warningDiagnostic(
        'source_text_too_large',
        `Operation ${field} exceeds ${MAX_SOURCE_TEXT_CHARS} characters and was omitted.`,
      ),
    );
    return undefined;
  }

  return value;
}

function buildCandidate(
  root: JsonObject,
  pathItem: JsonObject,
  operation: JsonObject,
  family: OpenApiFamily,
  openapiVersion: string,
  pathTemplate: string,
  method: string,
  refBudget: LocalRefTraversalBudget,
): OpenApiImportCandidate {
  const pathPointer = `/paths/${encodePointerToken(pathTemplate)}`;
  const operationPointer = `${pathPointer}/${method}`;
  const diagnostics: ImportDiagnostic[] = [];

  const parameters = extractEffectiveParameters(
    root,
    pathItem,
    operation,
    pathPointer,
    operationPointer,
    refBudget,
  );
  diagnostics.push(...parameters.diagnostics);

  const security = extractEffectiveSecurity(root, operation);
  diagnostics.push(...security.diagnostics);

  const requestBodies = extractRequestBodies(
    root,
    operation,
    operationPointer,
    refBudget,
  );
  diagnostics.push(...requestBodies.diagnostics);

  const responses = extractResponses(
    root,
    operation,
    operationPointer,
    refBudget,
  );
  diagnostics.push(...responses.diagnostics);

  const operationId =
    typeof operation.operationId === 'string'
      ? operation.operationId
      : undefined;
  if (
    operation.operationId !== undefined &&
    typeof operation.operationId !== 'string'
  ) {
    diagnostics.push(
      blockingDiagnostic(
        'invalid_openapi_document',
        `Operation operationId at ${operationPointer} must be a string when present.`,
      ),
    );
  }

  const source = {
    openapiVersion,
    family,
    operationPointer,
    httpMethod: method,
    pathTemplate,
    ...(operationId === undefined ? {} : { operationId }),
  };

  const candidate: OpenApiImportCandidate = {
    source,
    parameters: parameters.parameters,
    requestBodies: requestBodies.evidence,
    responses: responses.evidence,
    security: security.security,
    unresolvedFields: ['inputSchema', 'outputSchema', 'surfaceSemantics'],
    diagnostics,
  };

  const summary = boundedSourceText(operation, 'summary', diagnostics);
  const description = boundedSourceText(operation, 'description', diagnostics);

  if (summary !== undefined) {
    candidate.sourceSummary = summary;
  }
  if (description !== undefined) {
    candidate.sourceDescription = description;
  }

  return candidate;
}

export function selectRootPathOperations(
  document: JsonObject,
  family: OpenApiFamily,
): OperationSelectionResult {
  const openapiVersion = document.openapi;
  if (typeof openapiVersion !== 'string') {
    return {
      candidates: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'Operation selection requires a string OpenAPI version.',
        ),
      ],
    };
  }

  const rawPaths = document.paths;
  if (rawPaths === undefined) {
    return { candidates: [], diagnostics: [] };
  }
  if (!isJsonObject(rawPaths)) {
    return {
      candidates: [],
      diagnostics: [
        blockingDiagnostic(
          'invalid_openapi_document',
          'OpenAPI paths must be an object when present.',
        ),
      ],
    };
  }

  const candidates: OpenApiImportCandidate[] = [];
  const diagnostics: ImportDiagnostic[] = [];
  const methods = family === '3.2' ? METHODS_32 : METHODS_31;
  const refBudget = new LocalRefTraversalBudget();
  let operationCount = 0;

  outer:
  for (const pathTemplate of Object.keys(rawPaths).sort()) {
    const rawPathItem = rawPaths[pathTemplate];
    if (rawPathItem === undefined) {
      continue;
    }

    const pathItemResult = resolvePathItem(
      document,
      rawPathItem,
      refBudget,
    );
    diagnostics.push(...pathItemResult.diagnostics);

    const pathItem = pathItemResult.value;
    if (pathItem === null) {
      continue;
    }

    if (family === '3.2' && hasOwn(pathItem, 'additionalOperations')) {
      diagnostics.push(
        blockingDiagnostic(
          'additional_operation_unsupported',
          `OpenAPI 3.2 additionalOperations at /paths/${encodePointerToken(pathTemplate)} are unsupported in v1.`,
        ),
      );
    }

    for (const method of methods) {
      const rawOperation = pathItem[method];
      if (rawOperation === undefined) {
        continue;
      }

      operationCount += 1;
      if (operationCount > MAX_OPERATIONS) {
        diagnostics.push(
          blockingDiagnostic(
            'operation_limit_exceeded',
            `Operation count exceeds the ${MAX_OPERATIONS}-operation limit.`,
          ),
        );
        break outer;
      }

      if (!isJsonObject(rawOperation)) {
        diagnostics.push(
          blockingDiagnostic(
            'invalid_openapi_document',
            `Operation ${method.toUpperCase()} ${pathTemplate} must be an object.`,
          ),
        );
        continue;
      }

      candidates.push(
        buildCandidate(
          document,
          pathItem,
          rawOperation,
          family,
          openapiVersion,
          pathTemplate,
          method,
          refBudget,
        ),
      );
    }
  }

  return { candidates, diagnostics };
}
