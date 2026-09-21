import { describe, expect, it } from 'vitest';

import type {
  OpenApiImportCandidate,
  OpenApiSourceProvenance,
} from '../src/candidate.js';
import type { JsonObject, JsonValue } from '../src/json-value.js';
import {
  materializeActionDefinition,
  materializeActionDefinitions,
} from '../src/materialize/action-definition-materializer.js';
import type {
  ImportResolution,
  ContextRequirement,
} from '../src/resolution.js';
import { validateCanonicalActionDefinition } from './support/canonical-action-validator.js';

function object(entries: Record<string, JsonValue> = {}): JsonObject {
  return Object.assign(Object.create(null) as JsonObject, entries);
}

function provenance(
  extra: Partial<OpenApiSourceProvenance> = {},
): OpenApiSourceProvenance {
  return {
    openapiVersion: '3.2.1',
    family: '3.2',
    operationPointer: '/paths/~1items/post',
    operationId: 'Source.Operation_ID',
    httpMethod: 'post',
    pathTemplate: '/items',
    ...extra,
  };
}

function candidate(
  extra: Partial<OpenApiImportCandidate> = {},
): OpenApiImportCandidate {
  return {
    source: provenance(),
    parameters: [],
    requestBodies: [],
    responses: [],
    security: {
      source: 'operation_override',
      requirements: [
        {
          anonymous: false,
          schemes: [
            { scheme: 'OAuth', scopes: ['admin', 'tenant:blue'] },
          ],
        },
      ],
      anonymousAlternative: false,
      inheritedSecurityRemoved: false,
    },
    suggestedInputSchema: object({
      type: 'object',
      properties: object({
        name: object({ type: 'string' }),
      }),
      required: ['name'],
      additionalProperties: false,
    }),
    suggestedOutputSchema: object({
      type: 'object',
      properties: object({
        id: object({ type: 'string' }),
      }),
      required: ['id'],
      additionalProperties: false,
    }),
    unresolvedFields: ['surfaceSemantics'],
    diagnostics: [],
    ...extra,
  };
}

function resolution(
  extra: Partial<ImportResolution> = {},
): ImportResolution {
  return {
    id: 'inventory.items.create',
    version: 1,
    title: 'Create item',
    description: 'Creates one inventory item.',
    scope: 'portable',
    effect: 'reversible_write',
    risk: 'moderate',
    idempotency: 'recommended_key',
    outputSensitivity: 'normal',
    outputContentTrust: 'trusted_application_data',
    contextRequirements: ['authenticated_actor', 'tenant'],
    inputSchema: { kind: 'candidate_suggestion' },
    outputSchema: { kind: 'candidate_suggestion' },
    ...extra,
  };
}

function materializedDefinition(
  sourceCandidate = candidate(),
  sourceResolution = resolution(),
): JsonObject {
  const result = materializeActionDefinition(sourceCandidate, sourceResolution);

  expect(result.diagnostics).toEqual([]);
  expect(result.materialized).not.toBeNull();

  if (result.materialized === null) {
    throw new Error('Expected materialization to succeed.');
  }

  return result.materialized.actionDefinition;
}

describe('explicit semantic completion', () => {
  it('materializes only explicit SurfaceRelay semantics with separate provenance', () => {
    const sourceCandidate = candidate();
    const result = materializeActionDefinition(
      sourceCandidate,
      resolution({
        contextRequirements: [
          'tenant',
          'authenticated_actor',
        ],
      }),
    );

    expect(result.diagnostics).toEqual([]);
    expect(result.materialized?.sourceProvenance).toEqual(sourceCandidate.source);
    expect(result.materialized?.sourceProvenance).not.toBe(sourceCandidate.source);

    expect(result.materialized?.actionDefinition).toEqual(
      object({
        id: 'inventory.items.create',
        version: 1,
        title: 'Create item',
        description: 'Creates one inventory item.',
        inputSchema: sourceCandidate.suggestedInputSchema as JsonObject,
        outputSchema: sourceCandidate.suggestedOutputSchema as JsonObject,
        scope: 'portable',
        effect: 'reversible_write',
        risk: 'moderate',
        idempotency: 'recommended_key',
        outputSensitivity: 'normal',
        outputContentTrust: 'trusted_application_data',
        contextRequirements: ['authenticated_actor', 'tenant'],
      }),
    );

    const canonical = validateCanonicalActionDefinition(
      result.materialized?.actionDefinition,
    );
    expect(canonical.valid, canonical.errors.join('\n')).toBe(true);
  });

  it.each([
    'id',
    'version',
    'title',
    'description',
    'scope',
    'effect',
    'risk',
    'idempotency',
    'outputSensitivity',
    'outputContentTrust',
    'contextRequirements',
    'inputSchema',
    'outputSchema',
  ] as const)('rejects missing explicit resolution field %s', (field) => {
    const full = resolution() as unknown as Record<string, unknown>;
    delete full[field];

    const result = materializeActionDefinition(
      candidate(),
      full as unknown as ImportResolution,
    );

    expect(result.materialized).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      field === 'id' || field === 'version'
        ? 'invalid_action_identity'
        : 'missing_surface_semantics',
    );
  });

  it('never fills missing semantics from OpenAPI method, operationId, or security evidence', () => {
    const incomplete = {
      title: 'Explicit title',
      description: 'Explicit description',
      inputSchema: { kind: 'candidate_suggestion' },
      outputSchema: { kind: 'candidate_suggestion' },
    } as unknown as ImportResolution;

    const result = materializeActionDefinition(
      candidate({
        source: provenance({
          operationId: 'admin.items.delete',
          httpMethod: 'delete',
        }),
      }),
      incomplete,
    );

    expect(result.materialized).toBeNull();
    expect(result.diagnostics.some(
      (diagnostic) =>
        diagnostic.code === 'invalid_action_identity' ||
        diagnostic.code === 'missing_surface_semantics',
    )).toBe(true);
  });
});

describe('canonical identity and metadata constraints', () => {
  it.each([
    'Inventory.Items.Create',
    'single',
    '.broken',
    'inventory..create',
    'inventory.items.Create',
    'inventory.items-create',
  ])('rejects invalid action id %s without normalization', (id) => {
    const result = materializeActionDefinition(
      candidate(),
      resolution({ id }),
    );

    expect(result.materialized).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'invalid_action_identity',
    );
  });

  it('rejects an action id above the 160-byte canonical limit', () => {
    const longSegment = 'a'.repeat(161);
    const result = materializeActionDefinition(
      candidate(),
      resolution({ id: `inventory.${longSegment}` }),
    );

    expect(result.materialized).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'invalid_action_identity',
    );
  });

  it.each([0, -1, 1.5, Number.NaN])('rejects invalid action version %s', (version) => {
    const result = materializeActionDefinition(
      candidate(),
      resolution({ version }),
    );

    expect(result.materialized).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'invalid_action_identity',
    );
  });

  it('enforces Unicode code-point title and description lengths without trimming', () => {
    const tooLongTitle = '🙂'.repeat(121);
    const tooLongDescription = '🙂'.repeat(2001);

    for (const sourceResolution of [
      resolution({ title: '' }),
      resolution({ title: tooLongTitle }),
      resolution({ description: '' }),
      resolution({ description: tooLongDescription }),
    ]) {
      const result = materializeActionDefinition(candidate(), sourceResolution);
      expect(result.materialized).toBeNull();
      expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
        'missing_surface_semantics',
      );
    }

    const whitespace = materializeActionDefinition(
      candidate(),
      resolution({ title: ' ', description: ' ' }),
    );
    expect(whitespace.diagnostics).toEqual([]);
    expect(whitespace.materialized).not.toBeNull();
  });

  it('rejects duplicate or unknown trusted context requirements', () => {
    const cases = [
      ['tenant', 'tenant'],
      ['authenticated_actor', 'role:admin'],
    ] as unknown as ContextRequirement[][];

    for (const contextRequirements of cases) {
      const result = materializeActionDefinition(
        candidate(),
        resolution({ contextRequirements }),
      );

      expect(result.materialized).toBeNull();
      expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
        'missing_surface_semantics',
      );
    }
  });

  it('canonicalizes context requirements to protocol enum declaration order', () => {
    const definition = materializedDefinition(
      candidate(),
      resolution({
        contextRequirements: [
          'human_confirmation',
          'tenant',
          'authenticated_actor',
          'browser_session',
        ],
      }),
    );

    expect(definition.contextRequirements).toEqual([
      'authenticated_actor',
      'tenant',
      'browser_session',
      'human_confirmation',
    ]);
  });
});

describe('schema resolution and copy safety', () => {
  it('requires a present non-blocked candidate input/output suggestion', () => {
    const sourceCandidate = candidate({
      suggestedInputSchema: undefined,
      suggestedOutputSchema: undefined,
    });

    const missing = materializeActionDefinition(
      sourceCandidate,
      resolution(),
    );
    expect(missing.materialized).toBeNull();
    expect(missing.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'missing_surface_semantics',
    );

    const blocked = materializeActionDefinition(
      candidate({
        diagnostics: [{
          code: 'invalid_openapi_document',
          message: 'Blocked source.',
          blocking: true,
        }],
      }),
      resolution(),
    );
    expect(blocked.materialized).toBeNull();
    expect(blocked.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'missing_surface_semantics',
    );
  });

  it('accepts candidate-suggested null output schema explicitly', () => {
    const definition = materializedDefinition(
      candidate({ suggestedOutputSchema: null }),
      resolution(),
    );

    expect(definition).toHaveProperty('outputSchema', null);
  });

  it('deep-copies explicit schemas and never retains caller-owned mutable objects', () => {
    const input = object({
      type: 'object',
      properties: object({ name: object({ type: 'string' }) }),
    });
    const output = object({
      type: 'object',
      properties: object({ id: object({ type: 'string' }) }),
    });

    const result = materializeActionDefinition(
      candidate(),
      resolution({
        inputSchema: { kind: 'explicit', schema: input },
        outputSchema: { kind: 'explicit', schema: output },
      }),
    );

    expect(result.diagnostics).toEqual([]);
    const definition = result.materialized?.actionDefinition;
    expect(definition?.inputSchema).toEqual(input);
    expect(definition?.outputSchema).toEqual(output);
    expect(definition?.inputSchema).not.toBe(input);
    expect(definition?.outputSchema).not.toBe(output);
    expect((definition?.inputSchema as JsonObject).properties).not.toBe(
      input.properties,
    );

    (input.properties as JsonObject).name = object({ type: 'number' });
    expect(definition?.inputSchema).not.toEqual(input);
  });

  it('also deep-copies candidate suggestions', () => {
    const sourceCandidate = candidate();
    const result = materializeActionDefinition(
      sourceCandidate,
      resolution(),
    );

    expect(result.diagnostics).toEqual([]);
    expect(result.materialized?.actionDefinition.inputSchema).not.toBe(
      sourceCandidate.suggestedInputSchema,
    );
    expect(result.materialized?.actionDefinition.outputSchema).not.toBe(
      sourceCandidate.suggestedOutputSchema,
    );
  });

  it('enforces explicit schema depth and 5,000-node budgets without whitelist restriction', () => {
    let deep: JsonObject = object({ type: 'string' });
    for (let index = 0; index < 70; index += 1) {
      deep = object({ nested: deep });
    }

    const wide = object();
    for (let index = 0; index < 5_100; index += 1) {
      wide[`x${index}`] = index;
    }

    for (const schema of [deep, wide]) {
      const result = materializeActionDefinition(
        candidate(),
        resolution({
          inputSchema: { kind: 'explicit', schema },
        }),
      );

      expect(result.materialized).toBeNull();
      expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
        'missing_surface_semantics',
      );
    }
  });

  it('allows caller-authored canonical JSON Schema keywords outside the importer suggestion whitelist', () => {
    const explicit = object({
      type: 'string',
      format: 'uuid',
      description: 'Explicit trusted schema annotation.',
    });

    const definition = materializedDefinition(
      candidate(),
      resolution({
        inputSchema: { kind: 'explicit', schema: explicit },
      }),
    );

    expect(definition.inputSchema).toEqual(explicit);
  });
});

describe('provenance separation and canonical validation', () => {
  it('never embeds OpenAPI provenance or runtime binding data into Action Definition', () => {
    const sourceCandidate = candidate();
    const result = materializeActionDefinition(
      sourceCandidate,
      resolution(),
    );

    expect(result.materialized?.sourceProvenance).toEqual(sourceCandidate.source);
    const definition = result.materialized?.actionDefinition as JsonObject;

    expect(definition).not.toHaveProperty('operationId');
    expect(definition).not.toHaveProperty('httpMethod');
    expect(definition).not.toHaveProperty('pathTemplate');
    expect(definition).not.toHaveProperty('operationPointer');
    expect(definition).not.toHaveProperty('extensions');
    expect(definition).not.toHaveProperty('runtimeBinding');

    const canonical = validateCanonicalActionDefinition(definition);
    expect(canonical.valid, canonical.errors.join('\n')).toBe(true);
  });

  it('passes every enum dimension through canonical validation', () => {
    const definition = materializedDefinition(
      candidate(),
      resolution({
        scope: 'headless',
        effect: 'external_side_effect',
        risk: 'consequential',
        idempotency: 'required_key',
        outputSensitivity: 'sensitive',
        outputContentTrust: 'contains_untrusted_content',
        contextRequirements: ['human_confirmation'],
      }),
    );

    const canonical = validateCanonicalActionDefinition(definition);
    expect(canonical.valid, canonical.errors.join('\n')).toBe(true);
  });
});

describe('batch materialization identity collisions', () => {
  it('rejects duplicate final id + version across different OpenAPI candidates', () => {
    const result = materializeActionDefinitions([
      {
        candidate: candidate({
          source: provenance({ operationPointer: '/paths/~1a/post' }),
        }),
        resolution: resolution({ id: 'inventory.items.create', version: 1 }),
      },
      {
        candidate: candidate({
          source: provenance({ operationPointer: '/paths/~1b/post' }),
        }),
        resolution: resolution({ id: 'inventory.items.create', version: 1 }),
      },
    ]);

    expect(result.materialized).toEqual([]);
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'duplicate_action_identity',
    );
  });

  it('accepts the same id at different explicit versions', () => {
    const result = materializeActionDefinitions([
      {
        candidate: candidate({
          source: provenance({ operationPointer: '/paths/~1a/post' }),
        }),
        resolution: resolution({ id: 'inventory.items.create', version: 1 }),
      },
      {
        candidate: candidate({
          source: provenance({ operationPointer: '/paths/~1b/post' }),
        }),
        resolution: resolution({ id: 'inventory.items.create', version: 2 }),
      },
    ]);

    expect(result.diagnostics).toEqual([]);
    expect(result.materialized).toHaveLength(2);
  });
});
