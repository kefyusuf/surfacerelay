import { readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import type { OpenApiImportCandidate } from '../src/candidate.js';
import type { JsonObject, JsonValue } from '../src/json-value.js';
import { MAX_SCHEMA_NODES_PER_FRAGMENT } from '../src/limits.js';
import { selectRootPathOperations } from '../src/operations/operation-selector.js';
import { copySupportedSchema } from '../src/schema/schema-subset.js';
import { applySchemaSuggestions } from '../src/schema/schema-suggestion.js';

function object(entries: Record<string, JsonValue> = {}): JsonObject {
  return Object.assign(Object.create(null) as JsonObject, entries);
}

function document(
  extra: Record<string, JsonValue> = {},
): JsonObject {
  return object({
    openapi: '3.2.1',
    info: object({ title: 'Fixture', version: '1' }),
    paths: object(),
    ...extra,
  });
}

function candidate(
  extra: Partial<OpenApiImportCandidate> = {},
): OpenApiImportCandidate {
  return {
    source: {
      openapiVersion: '3.2.1',
      family: '3.2',
      operationPointer: '/paths/~1items/post',
      httpMethod: 'post',
      pathTemplate: '/items',
    },
    parameters: [],
    requestBodies: [],
    responses: [
      {
        statusCode: '200',
        content: [],
        sourcePointer: '/paths/~1items/post/responses/200',
      },
    ],
    security: {
      source: 'none',
      requirements: [],
      anonymousAlternative: false,
      inheritedSecurityRemoved: false,
    },
    unresolvedFields: ['inputSchema', 'outputSchema', 'surfaceSemantics'],
    diagnostics: [],
    ...extra,
  };
}

function fixture(path: string): JsonObject {
  return JSON.parse(
    readFileSync(new URL(path, import.meta.url), 'utf8'),
  ) as JsonObject;
}

describe('conservative schema subset', () => {
  it('loads positive/negative schema fixtures as executable contract evidence', () => {
    expect(
      copySupportedSchema(
        document(),
        fixture('./fixtures/positive/schema-supported.json'),
      ).schema,
    ).not.toBeNull();

    expect(
      copySupportedSchema(
        document(),
        fixture('./fixtures/negative/schema-unsupported-format.json'),
      ).diagnostics.map((diagnostic) => diagnostic.code),
    ).toContain('schema_keyword_unsupported');
  });
  it('accepts the approved whitelist and copies semantics without mutation', () => {
    const source = object({
      type: 'object',
      properties: object({
        name: object({
          type: 'string',
          minLength: 1,
          maxLength: 120,
          pattern: '^[A-Z]',
          enum: ['Alice', 'Bob'],
        }),
        count: object({
          type: 'integer',
          minimum: 0,
          maximum: 100,
          exclusiveMinimum: -1,
          exclusiveMaximum: 101,
          multipleOf: 1,
        }),
        tags: object({
          type: 'array',
          items: object({ type: 'string' }),
          minItems: 1,
          maxItems: 5,
          uniqueItems: true,
        }),
        metadata: object({
          type: ['object', 'null'],
          minProperties: 0,
          maxProperties: 10,
          additionalProperties: object({ type: 'string' }),
        }),
        enabled: object({ const: true }),
      }),
      required: ['name'],
      additionalProperties: false,
    });

    const result = copySupportedSchema(document(), source);

    expect(result.diagnostics).toEqual([]);
    expect(result.schema).toEqual(source);
    expect(result.schema).not.toBe(source);
    expect(result.schema?.properties).not.toBe(source.properties);
  });

  it('accepts the default OAS dialect when jsonSchemaDialect is absent', () => {
    const result = copySupportedSchema(
      document(),
      object({ type: 'string' }),
    );

    expect(result.diagnostics).toEqual([]);
    expect(result.schema).toEqual(object({ type: 'string' }));
  });

  it('rejects explicit document jsonSchemaDialect in v1', () => {
    const result = copySupportedSchema(
      document({
        jsonSchemaDialect: 'https://json-schema.org/draft/2020-12/schema',
      }),
      object({ type: 'string' }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'unsupported_schema_dialect',
    ]);
  });

  it('rejects schema-local $schema', () => {
    const result = copySupportedSchema(
      document(),
      object({
        $schema: 'https://json-schema.org/draft/2020-12/schema',
        type: 'string',
      }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'unsupported_schema_dialect',
    ]);
  });

  it.each([
    'allOf',
    'anyOf',
    'oneOf',
    'not',
    'if',
    'then',
    'else',
    'prefixItems',
    'contains',
    'unevaluatedProperties',
    'unevaluatedItems',
    'patternProperties',
    'propertyNames',
    'dependentSchemas',
    'dependentRequired',
    'format',
    'readOnly',
    'writeOnly',
    'discriminator',
    'xml',
    'externalDocs',
    'deprecated',
    'examples',
    'example',
    'contentEncoding',
    'contentMediaType',
    'title',
    'description',
    'x-custom',
  ])('rejects unsupported schema keyword %s instead of dropping it', (keyword) => {
    const schema = object({ type: 'string' });
    schema[keyword] = keyword === 'deprecated' ? true : 'unsupported';

    const result = copySupportedSchema(document(), schema);

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_keyword_unsupported',
    );
  });

  it('rejects $ref with sibling schema keywords', () => {
    const result = copySupportedSchema(
      document({
        components: object({
          schemas: object({
            Name: object({ type: 'string' }),
          }),
        }),
      }),
      object({
        $ref: '#/components/schemas/Name',
        maxLength: 20,
      }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'schema_ref_sibling_unsupported',
    ]);
  });

  it('inlines local acyclic schema references without retaining source refs', () => {
    const source = object({
      type: 'object',
      properties: object({
        name: object({ $ref: '#/components/schemas/Name' }),
      }),
    });
    const result = copySupportedSchema(
      document({
        components: object({
          schemas: object({
            Name: object({ type: 'string', minLength: 1 }),
          }),
        }),
      }),
      source,
    );

    expect(result.diagnostics).toEqual([]);
    expect(result.schema).toEqual(
      object({
        type: 'object',
        properties: object({
          name: object({ type: 'string', minLength: 1 }),
        }),
      }),
    );
    expect(JSON.stringify(result.schema)).not.toContain('$ref');
  });

  it('fails closed when a schema reference resolves to JSON null', () => {
    const result = copySupportedSchema(
      document({
        components: object({
          schemas: object({ Broken: null }),
        }),
      }),
      object({ $ref: '#/components/schemas/Broken' }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_keyword_unsupported',
    );
  });

  it('rejects cyclic schema references', () => {
    const doc = document({
      components: object({
        schemas: object({
          A: object({ $ref: '#/components/schemas/B' }),
          B: object({ $ref: '#/components/schemas/A' }),
        }),
      }),
    });

    const result = copySupportedSchema(
      doc,
      object({ $ref: '#/components/schemas/A' }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ref_cycle',
    );
  });

  it('enforces the per-fragment schema node budget', () => {
    const properties = object();
    for (let index = 0; index < MAX_SCHEMA_NODES_PER_FRAGMENT; index += 1) {
      properties[`p${index}`] = object({ type: 'string' });
    }

    const result = copySupportedSchema(
      document(),
      object({ type: 'object', properties }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_limit_exceeded',
    );
  });

  it('counts required/type scalar arrays against the schema fragment budget', () => {
    const required = Array.from(
      { length: MAX_SCHEMA_NODES_PER_FRAGMENT },
      (_, index) => `p${index}`,
    );

    const result = copySupportedSchema(
      document(),
      object({ type: 'object', required }),
    );

    expect(result.schema).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_limit_exceeded',
    );
  });

  it('rejects invalid shapes for approved keywords rather than coercing them', () => {
    const invalid = [
      object({ type: 'wat' }),
      object({ required: ['a', 'a'] }),
      object({ minLength: -1 }),
      object({ multipleOf: 0 }),
      object({ properties: [] }),
      object({ items: 'not-a-schema' }),
      object({ uniqueItems: 'yes' }),
      object({ pattern: '[' }),
    ];

    for (const schema of invalid) {
      const result = copySupportedSchema(document(), schema);
      expect(result.schema).toBeNull();
      expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
        'schema_keyword_unsupported',
      );
    }
  });
});

describe('safe input suggestions', () => {
  it('does not create suggestions when prior structural evidence is blocking', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        diagnostics: [{
          code: 'invalid_openapi_document',
          message: 'Malformed source evidence.',
          blocking: true,
        }],
        responses: [{
          statusCode: '204',
          content: [],
          sourcePointer: '/responses/204',
        }],
      }),
    );

    expect(result.suggestedInputSchema).toBeUndefined();
    expect(result.suggestedOutputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('inputSchema');
    expect(result.unresolvedFields).toContain('outputSchema');
    expect(result.diagnostics).toHaveLength(1);
  });
  it('suggests the exact empty object schema when there are no parameters or request body', () => {
    const result = applySchemaSuggestions(document(), candidate());

    expect(result.suggestedInputSchema).toEqual(
      object({
        type: 'object',
        properties: object(),
        additionalProperties: false,
      }),
    );
    expect(result.unresolvedFields).not.toContain('inputSchema');
  });

  it('suggests exactly one application/json request body schema', () => {
    const requestSchema = object({
      type: 'object',
      properties: object({ name: object({ type: 'string' }) }),
      required: ['name'],
      additionalProperties: false,
    });

    const result = applySchemaSuggestions(
      document(),
      candidate({
        requestBodies: [{
          required: true,
          content: [{
            mediaType: 'application/json',
            schema: requestSchema,
            sourcePointer: '/paths/~1items/post/requestBody/content/application~1json',
          }],
          sourcePointer: '/paths/~1items/post/requestBody',
        }],
      }),
    );

    expect(result.suggestedInputSchema).toEqual(requestSchema);
    expect(result.suggestedInputSchema).not.toBe(requestSchema);
    expect(result.unresolvedFields).not.toContain('inputSchema');
  });

  it.each([
    candidate({
      parameters: [{
        name: 'q',
        in: 'query',
        required: false,
        sourcePointer: '/paths/~1items/get/parameters/0',
      }],
    }),
    candidate({
      requestBodies: [{
        required: false,
        content: [
          { mediaType: 'application/json', schema: object({ type: 'object' }), sourcePointer: '/json' },
          { mediaType: 'application/xml', schema: object({ type: 'object' }), sourcePointer: '/xml' },
        ],
        sourcePointer: '/requestBody',
      }],
    }),
    candidate({
      requestBodies: [{
        required: false,
        content: [
          { mediaType: 'application/json', sourcePointer: '/json' },
        ],
        sourcePointer: '/requestBody',
      }],
    }),
  ])('leaves ambiguous transport input unresolved', (sourceCandidate) => {
    const result = applySchemaSuggestions(document(), sourceCandidate);

    expect(result.suggestedInputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('inputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ambiguous_input_mapping',
    );
  });

  it('leaves input unresolved when its schema subset is unsupported', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        requestBodies: [{
          required: true,
          content: [{
            mediaType: 'application/json',
            schema: object({ type: 'string', format: 'uuid' }),
            sourcePointer: '/json',
          }],
          sourcePointer: '/requestBody',
        }],
      }),
    );

    expect(result.suggestedInputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('inputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_keyword_unsupported',
    );
  });
});

describe('cross-dimension blocking consistency', () => {
  it('suppresses output suggestion when input mapping is blocking', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        parameters: [{
          name: 'q',
          in: 'query',
          required: false,
          sourcePointer: '/paths/~1items/get/parameters/0',
        }],
        responses: [{
          statusCode: '200',
          content: [{
            mediaType: 'application/json',
            schema: object({ type: 'object' }),
            sourcePointer: '/responses/200/content/application~1json',
          }],
          sourcePointer: '/responses/200',
        }],
      }),
    );

    expect(result.suggestedInputSchema).toBeUndefined();
    expect(result.suggestedOutputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('inputSchema');
    expect(result.unresolvedFields).toContain('outputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ambiguous_input_mapping',
    );
  });

  it('suppresses input suggestion when output selection is blocking', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        requestBodies: [],
        responses: [
          { statusCode: '200', content: [], sourcePointer: '/responses/200' },
          { statusCode: '201', content: [], sourcePointer: '/responses/201' },
        ],
      }),
    );

    expect(result.suggestedInputSchema).toBeUndefined();
    expect(result.suggestedOutputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('inputSchema');
    expect(result.unresolvedFields).toContain('outputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ambiguous_success_output',
    );
  });
});

describe('safe output suggestions', () => {
  it('suggests null for one explicit successful response with no content', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        responses: [{
          statusCode: '204',
          content: [],
          sourcePointer: '/responses/204',
        }],
      }),
    );

    expect(result).toHaveProperty('suggestedOutputSchema', null);
    expect(result.unresolvedFields).not.toContain('outputSchema');
  });

  it('suggests one successful application/json response schema', () => {
    const responseSchema = object({
      type: 'object',
      properties: object({ id: object({ type: 'string' }) }),
      required: ['id'],
      additionalProperties: false,
    });
    const result = applySchemaSuggestions(
      document(),
      candidate({
        responses: [{
          statusCode: '201',
          content: [{
            mediaType: 'application/json',
            schema: responseSchema,
            sourcePointer: '/responses/201/content/application~1json',
          }],
          sourcePointer: '/responses/201',
        }],
      }),
    );

    expect(result.suggestedOutputSchema).toEqual(responseSchema);
    expect(result.suggestedOutputSchema).not.toBe(responseSchema);
    expect(result.unresolvedFields).not.toContain('outputSchema');
  });

  it.each([
    candidate({
      responses: [
        { statusCode: '200', content: [], sourcePointer: '/responses/200' },
        { statusCode: '201', content: [], sourcePointer: '/responses/201' },
      ],
    }),
    candidate({
      responses: [
        { statusCode: '2XX', content: [], sourcePointer: '/responses/2XX' },
      ],
    }),
    candidate({
      responses: [{
        statusCode: '200',
        content: [
          { mediaType: 'application/json', schema: object({ type: 'object' }), sourcePointer: '/json' },
          { mediaType: 'text/plain', schema: object({ type: 'string' }), sourcePointer: '/text' },
        ],
        sourcePointer: '/responses/200',
      }],
    }),
    candidate({ responses: [] }),
  ])('leaves ambiguous success output unresolved', (sourceCandidate) => {
    const result = applySchemaSuggestions(document(), sourceCandidate);

    expect(result.suggestedOutputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('outputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ambiguous_success_output',
    );
  });

  it('leaves output unresolved when response schema subset is unsupported', () => {
    const result = applySchemaSuggestions(
      document(),
      candidate({
        responses: [{
          statusCode: '200',
          content: [{
            mediaType: 'application/json',
            schema: object({ type: 'string', description: 'untrusted annotation' }),
            sourcePointer: '/json',
          }],
          sourcePointer: '/responses/200',
        }],
      }),
    );

    expect(result.suggestedOutputSchema).toBeUndefined();
    expect(result.unresolvedFields).toContain('outputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_keyword_unsupported',
    );
  });
});

describe('operation candidate integration', () => {
  it('adds suggestions to selected operations without adding SurfaceRelay policy semantics', () => {
    const doc = object({
      openapi: '3.2.1',
      info: object({ title: 'Fixture', version: '1' }),
      paths: object({
        '/items': object({
          post: object({
            requestBody: object({
              required: true,
              content: object({
                'application/json': object({
                  schema: object({
                    type: 'object',
                    properties: object({ name: object({ type: 'string' }) }),
                    required: ['name'],
                    additionalProperties: false,
                  }),
                }),
              }),
            }),
            responses: object({
              '201': object({
                description: 'created',
                content: object({
                  'application/json': object({
                    schema: object({
                      type: 'object',
                      properties: object({ id: object({ type: 'string' }) }),
                      required: ['id'],
                      additionalProperties: false,
                    }),
                  }),
                }),
              }),
            }),
          }),
        }),
      }),
    });

    const selected = selectRootPathOperations(doc, '3.2');
    const enriched = selected.candidates.map((item) =>
      applySchemaSuggestions(doc, item),
    );

    expect(enriched[0]?.suggestedInputSchema).toBeDefined();
    expect(enriched[0]?.suggestedOutputSchema).toBeDefined();

    const raw = enriched[0] as unknown as Record<string, unknown>;
    for (const field of ['id', 'version', 'scope', 'effect', 'risk', 'idempotency', 'contextRequirements']) {
      expect(raw).not.toHaveProperty(field);
    }
  });
});
