import { Buffer } from 'node:buffer';

import type {
  OpenApiImportCandidate,
  OpenApiSourceProvenance,
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
  MAX_DOCUMENT_DEPTH,
  MAX_SCHEMA_NODES_PER_FRAGMENT,
} from '../limits.js';
import type {
  ActionEffect,
  ActionRisk,
  ActionScope,
  ContextRequirement,
  IdempotencyPolicy,
  ImportResolution,
  OutputContentTrust,
  OutputSensitivity,
  OutputSchemaResolution,
  SchemaResolution,
} from '../resolution.js';

export interface MaterializedAction {
  actionDefinition: JsonObject;
  sourceProvenance: OpenApiSourceProvenance;
}

export interface MaterializationResult {
  materialized: MaterializedAction | null;
  diagnostics: ImportDiagnostic[];
}

export interface MaterializationRequest {
  candidate: OpenApiImportCandidate;
  resolution: ImportResolution;
}

export interface BatchMaterializationResult {
  materialized: MaterializedAction[];
  diagnostics: ImportDiagnostic[];
}

const ID_PATTERN = /^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/;

const SCOPES = new Set<ActionScope>([
  'portable',
  'page_scoped',
  'browser_local',
  'headless',
]);

const EFFECTS = new Set<ActionEffect>([
  'read',
  'reversible_write',
  'destructive_write',
  'external_side_effect',
]);

const RISKS = new Set<ActionRisk>([
  'low',
  'moderate',
  'high',
  'consequential',
]);

const IDEMPOTENCY = new Set<IdempotencyPolicy>([
  'none',
  'recommended_key',
  'required_key',
]);

const OUTPUT_SENSITIVITY = new Set<OutputSensitivity>([
  'normal',
  'sensitive',
]);

const OUTPUT_CONTENT_TRUST = new Set<OutputContentTrust>([
  'trusted_application_data',
  'contains_untrusted_content',
]);

const CONTEXT_REQUIREMENT_ORDER: readonly ContextRequirement[] = [
  'authenticated_actor',
  'tenant',
  'current_record',
  'current_selection',
  'browser_session',
  'human_confirmation',
];

const CONTEXT_REQUIREMENTS = new Set<ContextRequirement>(
  CONTEXT_REQUIREMENT_ORDER,
);

interface CopyWork {
  source: unknown;
  depth: number;
  parent: JsonObject | JsonValue[] | null;
  key: string | number | null;
}

class SchemaCopyError extends Error {
  constructor(message: string) {
    super(message);
    this.name = 'SchemaCopyError';
  }
}

function diagnostic(
  candidate: OpenApiImportCandidate,
  code: 'missing_surface_semantics' | 'invalid_action_identity',
  message: string,
): ImportDiagnostic {
  return {
    ...blockingDiagnostic(code, message),
    sourcePointer: candidate.source.operationPointer,
  };
}

function assignCopy(
  work: CopyWork,
  value: JsonValue,
  setRoot: (value: JsonValue) => void,
): void {
  if (work.parent === null) {
    setRoot(value);
    return;
  }

  if (Array.isArray(work.parent)) {
    work.parent[work.key as number] = value;
    return;
  }

  work.parent[work.key as string] = value;
}

function copyBoundedSchema(source: unknown): JsonObject {
  if (!isJsonObject(source)) {
    throw new SchemaCopyError('Canonical schema must be an object.');
  }

  let root: JsonValue | undefined;
  let nodeCount = 0;
  const seen = new WeakSet<object>();
  const pending: CopyWork[] = [{
    source,
    depth: 1,
    parent: null,
    key: null,
  }];

  const consume = (depth: number, count = 1): void => {
    if (depth > MAX_DOCUMENT_DEPTH) {
      throw new SchemaCopyError(
        `Canonical schema exceeds the ${MAX_DOCUMENT_DEPTH}-level depth limit.`,
      );
    }

    nodeCount += count;
    if (nodeCount > MAX_SCHEMA_NODES_PER_FRAGMENT) {
      throw new SchemaCopyError(
        `Canonical schema exceeds the ${MAX_SCHEMA_NODES_PER_FRAGMENT}-node limit.`,
      );
    }
  };

  while (pending.length > 0) {
    const work = pending.pop();
    if (work === undefined) {
      break;
    }

    consume(work.depth);
    const value = work.source;

    if (
      value === null ||
      typeof value === 'string' ||
      typeof value === 'boolean'
    ) {
      assignCopy(work, value, (next) => {
        root = next;
      });
      continue;
    }

    if (typeof value === 'number') {
      if (!Number.isFinite(value)) {
        throw new SchemaCopyError(
          'Canonical schema numbers must be finite JSON numbers.',
        );
      }

      assignCopy(work, value, (next) => {
        root = next;
      });
      continue;
    }

    if (Array.isArray(value)) {
      if (seen.has(value)) {
        throw new SchemaCopyError(
          'Canonical schema must not contain repeated or cyclic object references.',
        );
      }
      seen.add(value);

      const target: JsonValue[] = new Array(value.length);
      assignCopy(work, target, (next) => {
        root = next;
      });

      for (let index = value.length - 1; index >= 0; index -= 1) {
        pending.push({
          source: value[index],
          depth: work.depth + 1,
          parent: target,
          key: index,
        });
      }
      continue;
    }

    if (typeof value === 'object') {
      const object = value as Record<string, unknown>;
      if (seen.has(object)) {
        throw new SchemaCopyError(
          'Canonical schema must not contain repeated or cyclic object references.',
        );
      }
      seen.add(object);

      const target = Object.create(null) as JsonObject;
      assignCopy(work, target, (next) => {
        root = next;
      });

      const entries = Object.entries(object);
      for (let index = entries.length - 1; index >= 0; index -= 1) {
        const entry = entries[index];
        if (entry === undefined) {
          continue;
        }

        const [key, child] = entry;
        consume(work.depth + 1); // Count the property name as part of the bounded graph.
        pending.push({
          source: child,
          depth: work.depth + 1,
          parent: target,
          key,
        });
      }
      continue;
    }

    throw new SchemaCopyError(
      `Canonical schema contains unsupported value type "${typeof value}".`,
    );
  }

  if (!isJsonObject(root)) {
    throw new SchemaCopyError('Canonical schema root must be an object.');
  }

  return root;
}

function cloneProvenance(
  source: OpenApiSourceProvenance,
): OpenApiSourceProvenance {
  return {
    openapiVersion: source.openapiVersion,
    family: source.family,
    operationPointer: source.operationPointer,
    ...(source.operationId === undefined
      ? {}
      : { operationId: source.operationId }),
    httpMethod: source.httpMethod,
    pathTemplate: source.pathTemplate,
  };
}

function textLength(value: string): number {
  return [...value].length;
}

function validateIdentity(
  candidate: OpenApiImportCandidate,
  raw: Record<string, unknown>,
  diagnostics: ImportDiagnostic[],
): { id: string | null; version: number | null } {
  const id = raw.id;
  if (
    typeof id !== 'string' ||
    ID_PATTERN.test(id) === false ||
    Buffer.byteLength(id, 'utf8') > 160
  ) {
    diagnostics.push(
      diagnostic(
        candidate,
        'invalid_action_identity',
        'Explicit Action id must match the canonical lowercase dot-separated grammar and 160-byte limit.',
      ),
    );
  }

  const version = raw.version;
  if (
    typeof version !== 'number' ||
    !Number.isInteger(version) ||
    !Number.isFinite(version) ||
    version < 1
  ) {
    diagnostics.push(
      diagnostic(
        candidate,
        'invalid_action_identity',
        'Explicit Action version must be a positive integer.',
      ),
    );
  }

  return {
    id: typeof id === 'string' && ID_PATTERN.test(id) && Buffer.byteLength(id, 'utf8') <= 160
      ? id
      : null,
    version:
      typeof version === 'number' &&
      Number.isInteger(version) &&
      Number.isFinite(version) &&
      version >= 1
        ? version
        : null,
  };
}

function validateText(
  candidate: OpenApiImportCandidate,
  raw: Record<string, unknown>,
  field: 'title' | 'description',
  max: number,
  diagnostics: ImportDiagnostic[],
): string | null {
  const value = raw[field];

  if (
    typeof value !== 'string' ||
    textLength(value) < 1 ||
    textLength(value) > max
  ) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        `Explicit ${field} must be a string between 1 and ${max} Unicode code points.`,
      ),
    );
    return null;
  }

  return value;
}

function validateEnum<T extends string>(
  candidate: OpenApiImportCandidate,
  raw: Record<string, unknown>,
  field: string,
  allowed: ReadonlySet<T>,
  diagnostics: ImportDiagnostic[],
): T | null {
  const value = raw[field];

  if (typeof value !== 'string' || !allowed.has(value as T)) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        `Explicit ${field} must be one of the canonical SurfaceRelay values.`,
      ),
    );
    return null;
  }

  return value as T;
}

function validateContextRequirements(
  candidate: OpenApiImportCandidate,
  raw: Record<string, unknown>,
  diagnostics: ImportDiagnostic[],
): ContextRequirement[] | null {
  const value = raw.contextRequirements;
  if (!Array.isArray(value)) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        'Explicit contextRequirements must be an array of canonical trusted-context requirements.',
      ),
    );
    return null;
  }

  const seen = new Set<ContextRequirement>();
  for (const requirement of value) {
    if (
      typeof requirement !== 'string' ||
      !CONTEXT_REQUIREMENTS.has(requirement as ContextRequirement) ||
      seen.has(requirement as ContextRequirement)
    ) {
      diagnostics.push(
        diagnostic(
          candidate,
          'missing_surface_semantics',
          'Explicit contextRequirements must contain unique canonical trusted-context requirements.',
        ),
      );
      return null;
    }

    seen.add(requirement as ContextRequirement);
  }

  return CONTEXT_REQUIREMENT_ORDER.filter((requirement) =>
    seen.has(requirement),
  );
}

function schemaResolutionObject(
  value: unknown,
): value is SchemaResolution {
  return (
    isJsonObject(value) &&
    (
      value.kind === 'candidate_suggestion' ||
      (
        value.kind === 'explicit' &&
        Object.prototype.hasOwnProperty.call(value, 'schema')
      )
    )
  );
}

function outputSchemaResolutionObject(
  value: unknown,
): value is OutputSchemaResolution {
  return (
    isJsonObject(value) &&
    (
      value.kind === 'candidate_suggestion' ||
      (
        value.kind === 'explicit' &&
        Object.prototype.hasOwnProperty.call(value, 'schema')
      )
    )
  );
}

function resolveInputSchema(
  candidate: OpenApiImportCandidate,
  rawResolution: unknown,
  diagnostics: ImportDiagnostic[],
): JsonObject | null {
  if (!schemaResolutionObject(rawResolution)) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        'Explicit inputSchema resolution choice is required.',
      ),
    );
    return null;
  }

  let source: unknown;
  if (rawResolution.kind === 'candidate_suggestion') {
    if (
      candidate.diagnostics.some((entry) => entry.blocking) ||
      candidate.suggestedInputSchema === undefined
    ) {
      diagnostics.push(
        diagnostic(
          candidate,
          'missing_surface_semantics',
          'Candidate input schema suggestion is unavailable or blocked.',
        ),
      );
      return null;
    }
    source = candidate.suggestedInputSchema;
  } else {
    source = rawResolution.schema;
  }

  try {
    return copyBoundedSchema(source);
  } catch (error) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        error instanceof Error
          ? error.message
          : 'Explicit input schema could not be copied safely.',
      ),
    );
    return null;
  }
}

function resolveOutputSchema(
  candidate: OpenApiImportCandidate,
  rawResolution: unknown,
  diagnostics: ImportDiagnostic[],
): { valid: boolean; schema: JsonObject | null } {
  if (!outputSchemaResolutionObject(rawResolution)) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        'Explicit outputSchema resolution choice is required.',
      ),
    );
    return { valid: false, schema: null };
  }

  let source: unknown;
  if (rawResolution.kind === 'candidate_suggestion') {
    if (
      candidate.diagnostics.some((entry) => entry.blocking) ||
      !Object.prototype.hasOwnProperty.call(candidate, 'suggestedOutputSchema') ||
      candidate.suggestedOutputSchema === undefined
    ) {
      diagnostics.push(
        diagnostic(
          candidate,
          'missing_surface_semantics',
          'Candidate output schema suggestion is unavailable or blocked.',
        ),
      );
      return { valid: false, schema: null };
    }
    source = candidate.suggestedOutputSchema;
  } else {
    source = rawResolution.schema;
  }

  if (source === null) {
    return { valid: true, schema: null };
  }

  try {
    return { valid: true, schema: copyBoundedSchema(source) };
  } catch (error) {
    diagnostics.push(
      diagnostic(
        candidate,
        'missing_surface_semantics',
        error instanceof Error
          ? error.message
          : 'Explicit output schema could not be copied safely.',
      ),
    );
    return { valid: false, schema: null };
  }
}

export function materializeActionDefinition(
  candidate: OpenApiImportCandidate,
  resolution: ImportResolution,
): MaterializationResult {
  const raw =
    resolution as unknown as Record<string, unknown>;
  const diagnostics: ImportDiagnostic[] = [];

  const identity = validateIdentity(candidate, raw, diagnostics);
  const title = validateText(
    candidate,
    raw,
    'title',
    120,
    diagnostics,
  );
  const description = validateText(
    candidate,
    raw,
    'description',
    2000,
    diagnostics,
  );
  const scope = validateEnum(
    candidate,
    raw,
    'scope',
    SCOPES,
    diagnostics,
  );
  const effect = validateEnum(
    candidate,
    raw,
    'effect',
    EFFECTS,
    diagnostics,
  );
  const risk = validateEnum(
    candidate,
    raw,
    'risk',
    RISKS,
    diagnostics,
  );
  const idempotency = validateEnum(
    candidate,
    raw,
    'idempotency',
    IDEMPOTENCY,
    diagnostics,
  );
  const outputSensitivity = validateEnum(
    candidate,
    raw,
    'outputSensitivity',
    OUTPUT_SENSITIVITY,
    diagnostics,
  );
  const outputContentTrust = validateEnum(
    candidate,
    raw,
    'outputContentTrust',
    OUTPUT_CONTENT_TRUST,
    diagnostics,
  );
  const contextRequirements = validateContextRequirements(
    candidate,
    raw,
    diagnostics,
  );

  const inputSchema = resolveInputSchema(
    candidate,
    raw.inputSchema,
    diagnostics,
  );
  const outputSchema = resolveOutputSchema(
    candidate,
    raw.outputSchema,
    diagnostics,
  );

  if (
    diagnostics.length > 0 ||
    identity.id === null ||
    identity.version === null ||
    title === null ||
    description === null ||
    scope === null ||
    effect === null ||
    risk === null ||
    idempotency === null ||
    outputSensitivity === null ||
    outputContentTrust === null ||
    contextRequirements === null ||
    inputSchema === null ||
    !outputSchema.valid
  ) {
    return { materialized: null, diagnostics };
  }

  const actionDefinition = Object.create(null) as JsonObject;
  actionDefinition.id = identity.id;
  actionDefinition.version = identity.version;
  actionDefinition.title = title;
  actionDefinition.description = description;
  actionDefinition.inputSchema = inputSchema;
  actionDefinition.outputSchema = outputSchema.schema;
  actionDefinition.scope = scope;
  actionDefinition.effect = effect;
  actionDefinition.risk = risk;
  actionDefinition.idempotency = idempotency;
  actionDefinition.outputSensitivity = outputSensitivity;
  actionDefinition.outputContentTrust = outputContentTrust;
  actionDefinition.contextRequirements = [...contextRequirements];

  return {
    materialized: {
      actionDefinition,
      sourceProvenance: cloneProvenance(candidate.source),
    },
    diagnostics: [],
  };
}

export function materializeActionDefinitions(
  requests: readonly MaterializationRequest[],
): BatchMaterializationResult {
  const materialized: MaterializedAction[] = [];
  const diagnostics: ImportDiagnostic[] = [];

  for (const request of requests) {
    const result = materializeActionDefinition(
      request.candidate,
      request.resolution,
    );

    diagnostics.push(...result.diagnostics);
    if (result.materialized !== null) {
      materialized.push(result.materialized);
    }
  }

  if (diagnostics.length > 0) {
    return { materialized: [], diagnostics };
  }

  const identities = new Map<string, MaterializedAction>();
  for (const action of materialized) {
    const id = action.actionDefinition.id;
    const version = action.actionDefinition.version;
    const key = `${String(id)}\u0000${String(version)}`;

    if (identities.has(key)) {
      return {
        materialized: [],
        diagnostics: [{
          ...blockingDiagnostic(
            'duplicate_action_identity',
            `Duplicate final Action identity ${String(id)} v${String(version)}.`,
          ),
          sourcePointer: action.sourceProvenance.operationPointer,
        }],
      };
    }

    identities.set(key, action);
  }

  return { materialized, diagnostics: [] };
}
