import { describe, expect, it } from 'vitest';

import {
  MAX_OPERATIONS,
  MAX_SOURCE_TEXT_CHARS,
} from '../src/limits.js';
import type { JsonObject, JsonValue } from '../src/json-value.js';
import { selectRootPathOperations } from '../src/operations/operation-selector.js';

function object(entries: Record<string, JsonValue> = {}): JsonObject {
  return Object.assign(Object.create(null) as JsonObject, entries);
}

function operation(entries: Record<string, JsonValue> = {}): JsonObject {
  return object({
    responses: object({
      '200': object({ description: 'ok' }),
    }),
    ...entries,
  });
}

function document(
  version: string,
  paths: JsonObject,
  extra: Record<string, JsonValue> = {},
): JsonObject {
  return object({
    openapi: version,
    info: object({ title: 'Fixture', version: '1' }),
    paths,
    ...extra,
  });
}

describe('root paths operation selection', () => {
  it('selects the fixed OpenAPI 3.1 methods only', () => {
    const methods = [
      'get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace',
    ];
    const pathItem = object();
    for (const method of methods) {
      pathItem[method] = operation({ operationId: `id-${method}` });
    }
    pathItem.query = operation({ operationId: 'should-not-import' });
    pathItem.callbacks = object({ ignored: operation() });

    const result = selectRootPathOperations(
      document('3.1.1', object({ '/items': pathItem })),
      '3.1',
    );

    expect(result.candidates.map((candidate) => candidate.source.httpMethod)).toEqual(methods);
    expect(result.candidates.map((candidate) => candidate.source.operationId)).not.toContain(
      'should-not-import',
    );
  });

  it('adds fixed QUERY for OpenAPI 3.2', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/search': object({
            query: operation({ operationId: 'Search_EXACT' }),
          }),
        }),
      ),
      '3.2',
    );

    expect(result.candidates).toHaveLength(1);
    expect(result.candidates[0]?.source).toMatchObject({
      operationId: 'Search_EXACT',
      httpMethod: 'query',
      pathTemplate: '/search',
      operationPointer: '/paths/~1search/query',
    });
  });

  it('never imports root webhooks or operation callbacks as root candidates', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            post: operation({
              callbacks: object({
                later: object({
                  '{$request.body#/callback}': object({
                    post: operation({ operationId: 'callback-op' }),
                  }),
                }),
              }),
            }),
          }),
        }),
        {
          webhooks: object({
            event: object({
              post: operation({ operationId: 'webhook-op' }),
            }),
          }),
        },
      ),
      '3.2',
    );

    expect(result.candidates).toHaveLength(1);
    expect(result.candidates.map((candidate) => candidate.source.operationId)).toEqual([
      undefined,
    ]);
  });

  it('reports OpenAPI 3.2 additionalOperations without importing them', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            get: operation(),
            additionalOperations: object({
              POLL: operation({ operationId: 'poll-items' }),
            }),
          }),
        }),
      ),
      '3.2',
    );

    expect(result.candidates).toHaveLength(1);
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'additional_operation_unsupported',
    );
  });

  it('fails closed on Path Item $ref with siblings', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            $ref: '#/components/pathItems/Items',
            get: operation(),
          }),
        }),
      ),
      '3.2',
    );

    expect(result.candidates).toEqual([]);
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'path_item_ref_sibling_ambiguous',
    ]);
  });

  it('resolves a same-document $ref-only Path Item', () => {
    const referenced = object({
      get: operation({ operationId: 'component-operation' }),
    });
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            $ref: '#/components/pathItems/Items',
          }),
        }),
        {
          components: object({
            pathItems: object({ Items: referenced }),
          }),
        },
      ),
      '3.2',
    );

    expect(result.diagnostics).toEqual([]);
    expect(result.candidates).toHaveLength(1);
    expect(result.candidates[0]?.source).toMatchObject({
      operationId: 'component-operation',
      pathTemplate: '/items',
      operationPointer: '/paths/~1items/get',
    });
  });

  it('stops deterministically after the operation budget', () => {
    const paths = object();

    for (let index = 0; index <= MAX_OPERATIONS; index += 1) {
      paths[`/p${String(index).padStart(4, '0')}`] = object({
        get: operation({ operationId: `op-${index}` }),
      });
    }

    const result = selectRootPathOperations(
      document('3.1.1', paths),
      '3.1',
    );

    expect(result.candidates).toHaveLength(MAX_OPERATIONS);
    expect(result.candidates[0]?.source.pathTemplate).toBe('/p0000');
    expect(result.candidates.at(-1)?.source.pathTemplate).toBe('/p0999');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'operation_limit_exceeded',
    );
  });

  it('counts malformed fixed-operation entries against the operation budget', () => {
    const paths = object();

    for (let index = 0; index <= MAX_OPERATIONS; index += 1) {
      paths[`/bad${String(index).padStart(4, '0')}`] = object({
        get: 'not-an-operation-object',
      });
    }

    const result = selectRootPathOperations(
      document('3.1.1', paths),
      '3.1',
    );

    expect(result.candidates).toEqual([]);
    expect(result.diagnostics.at(-1)?.code).toBe('operation_limit_exceeded');
    expect(
      result.diagnostics.filter(
        (diagnostic) => diagnostic.code === 'invalid_openapi_document',
      ),
    ).toHaveLength(MAX_OPERATIONS);
  });
});

describe('request/response source evidence', () => {
  it('preserves bounded media/schema fragments for Task 5 without materializing schemas', () => {
    const requestSchema = object({
      type: 'object',
      properties: object({
        name: object({ type: 'string' }),
      }),
    });
    const responseSchema = object({
      type: 'object',
      properties: object({
        id: object({ type: 'string' }),
      }),
    });

    const candidate = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            post: operation({
              requestBody: object({
                required: true,
                content: object({
                  'application/json': object({ schema: requestSchema }),
                }),
              }),
              responses: object({
                '201': object({
                  description: 'created',
                  content: object({
                    'application/json': object({ schema: responseSchema }),
                  }),
                }),
              }),
            }),
          }),
        }),
      ),
      '3.2',
    ).candidates[0];

    expect(candidate?.requestBodies).toHaveLength(1);
    expect(candidate?.requestBodies[0]).toMatchObject({
      required: true,
      sourcePointer: '/paths/~1items/post/requestBody',
    });
    expect(candidate?.requestBodies[0]?.content[0]).toMatchObject({
      mediaType: 'application/json',
      sourcePointer: '/paths/~1items/post/requestBody/content/application~1json',
    });
    expect(candidate?.requestBodies[0]?.content[0]?.schema).toBe(requestSchema);

    expect(candidate?.responses).toHaveLength(1);
    expect(candidate?.responses[0]).toMatchObject({
      statusCode: '201',
      sourcePointer: '/paths/~1items/post/responses/201',
    });
    expect(candidate?.responses[0]?.content[0]?.schema).toBe(responseSchema);

    expect(candidate?.suggestedInputSchema).toBeUndefined();
    expect(candidate?.suggestedOutputSchema).toBeUndefined();
  });
});

  it('resolves chained same-document request/response references before extracting evidence', () => {
    const requestSchema = object({
      type: 'object',
      properties: object({ name: object({ type: 'string' }) }),
    });
    const responseSchema = object({
      type: 'object',
      properties: object({ id: object({ type: 'string' }) }),
    });

    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            post: operation({
              requestBody: object({
                $ref: '#/components/requestBodies/A',
              }),
              responses: object({
                '201': object({
                  $ref: '#/components/responses/A',
                }),
              }),
            }),
          }),
        }),
        {
          components: object({
            requestBodies: object({
              A: object({ $ref: '#/components/requestBodies/B' }),
              B: object({
                required: true,
                content: object({
                  'application/json': object({ schema: requestSchema }),
                }),
              }),
            }),
            responses: object({
              A: object({ $ref: '#/components/responses/B' }),
              B: object({
                description: 'created',
                content: object({
                  'application/json': object({ schema: responseSchema }),
                }),
              }),
            }),
          }),
        },
      ),
      '3.2',
    );

    const candidate = result.candidates[0];
    expect(candidate?.diagnostics).toEqual([]);
    expect(candidate?.requestBodies[0]?.required).toBe(true);
    expect(candidate?.requestBodies[0]?.content[0]?.schema).toBe(requestSchema);
    expect(candidate?.responses[0]?.content[0]?.schema).toBe(responseSchema);
  });

  it('fails closed on a chained response reference cycle instead of treating it as no-content', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            get: operation({
              responses: object({
                '200': object({
                  $ref: '#/components/responses/A',
                }),
              }),
            }),
          }),
        }),
        {
          components: object({
            responses: object({
              A: object({ $ref: '#/components/responses/B' }),
              B: object({ $ref: '#/components/responses/A' }),
            }),
          }),
        },
      ),
      '3.2',
    );

    const candidate = result.candidates[0];
    expect(candidate?.responses).toEqual([]);
    expect(candidate?.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ref_cycle',
    );
  });

  it('fails closed when request/response references resolve to JSON null', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            post: operation({
              requestBody: object({
                $ref: '#/components/requestBodies/Broken',
              }),
              responses: object({
                '200': object({
                  $ref: '#/components/responses/Broken',
                }),
              }),
            }),
          }),
        }),
        {
          components: object({
            requestBodies: object({ Broken: null }),
            responses: object({ Broken: null }),
          }),
        },
      ),
      '3.2',
    );

    const candidate = result.candidates[0];
    expect(candidate?.requestBodies).toEqual([]);
    expect(candidate?.responses).toEqual([]);
    expect(
      candidate?.diagnostics.filter(
        (diagnostic) => diagnostic.code === 'invalid_openapi_document',
      ),
    ).toHaveLength(2);
  });

describe('exact provenance and source documentation evidence', () => {
  it('preserves operationId exactly and escapes the JSON Pointer path token', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/a~b/c': object({
            get: operation({
              operationId: 'Case_Sensitive-ID',
              summary: 'Exact summary',
              description: 'Exact **markdown**',
            }),
          }),
        }),
      ),
      '3.2',
    );

    expect(result.candidates[0]?.source).toEqual({
      openapiVersion: '3.2.1',
      family: '3.2',
      operationPointer: '/paths/~1a~0b~1c/get',
      operationId: 'Case_Sensitive-ID',
      httpMethod: 'get',
      pathTemplate: '/a~b/c',
    });
    expect(result.candidates[0]?.sourceSummary).toBe('Exact summary');
    expect(result.candidates[0]?.sourceDescription).toBe('Exact **markdown**');
  });

  it('omits oversized source prose instead of truncating it', () => {
    const oversized = 'x'.repeat(MAX_SOURCE_TEXT_CHARS + 1);
    const result = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            get: operation({
              summary: oversized,
              description: oversized,
            }),
          }),
        }),
      ),
      '3.1',
    );

    const candidate = result.candidates[0];
    expect(candidate?.sourceSummary).toBeUndefined();
    expect(candidate?.sourceDescription).toBeUndefined();
    expect(candidate?.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'source_text_too_large',
      'source_text_too_large',
    ]);
    expect(candidate?.diagnostics.every((diagnostic) => diagnostic.blocking === false)).toBe(true);
  });

  it('does not create SurfaceRelay semantics on candidates', () => {
    const result = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            delete: operation({
              operationId: 'deleteItem',
              security: [object({ OAuth: ['write:items'] })],
            }),
          }),
        }),
      ),
      '3.1',
    );

    const candidate = result.candidates[0] as unknown as Record<string, unknown>;
    for (const field of [
      'id',
      'version',
      'scope',
      'effect',
      'risk',
      'idempotency',
      'contextRequirements',
    ]) {
      expect(candidate).not.toHaveProperty(field);
    }
  });
});

describe('effective parameters', () => {
  it('inherits path parameters and lets operation parameters override by exact name + in', () => {
    const result = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items/{id}': object({
            parameters: [
              object({
                name: 'id',
                in: 'path',
                required: true,
                schema: object({ type: 'string' }),
              }),
              object({
                name: 'lang',
                in: 'query',
                schema: object({ type: 'string' }),
              }),
            ],
            get: operation({
              parameters: [
                object({
                  name: 'lang',
                  in: 'query',
                  required: true,
                  schema: object({ type: 'string', enum: ['tr', 'en'] }),
                }),
                object({
                  name: 'trace',
                  in: 'header',
                  schema: object({ type: 'string' }),
                }),
              ],
            }),
          }),
        }),
      ),
      '3.1',
    );

    expect(
      result.candidates[0]?.parameters.map((parameter) => ({
        name: parameter.name,
        in: parameter.in,
        required: parameter.required,
      })),
    ).toEqual([
      { name: 'id', in: 'path', required: true },
      { name: 'lang', in: 'query', required: true },
      { name: 'trace', in: 'header', required: false },
    ]);
  });

  it('resolves same-document referenced parameters before applying override identity', () => {
    const result = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            parameters: [
              object({ $ref: '#/components/parameters/Lang' }),
            ],
            get: operation({
              parameters: [
                object({
                  name: 'lang',
                  in: 'query',
                  required: true,
                }),
              ],
            }),
          }),
        }),
        {
          components: object({
            parameters: object({
              Lang: object({
                name: 'lang',
                in: 'query',
                schema: object({ type: 'string' }),
              }),
            }),
          }),
        },
      ),
      '3.1',
    );

    expect(result.candidates[0]?.parameters).toHaveLength(1);
    expect(result.candidates[0]?.parameters[0]).toMatchObject({
      name: 'lang',
      in: 'query',
      required: true,
    });
  });

  it('resolves chained same-document Parameter references to the terminal Parameter Object', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            get: operation({
              parameters: [
                object({ $ref: '#/components/parameters/A' }),
              ],
            }),
          }),
        }),
        {
          components: object({
            parameters: object({
              A: object({ $ref: '#/components/parameters/B' }),
              B: object({
                name: 'lang',
                in: 'query',
                required: true,
                schema: object({ type: 'string' }),
              }),
            }),
          }),
        },
      ),
      '3.2',
    );

    const candidate = result.candidates[0];
    expect(candidate?.diagnostics).toEqual([]);
    expect(candidate?.parameters).toHaveLength(1);
    expect(candidate?.parameters[0]).toMatchObject({
      name: 'lang',
      in: 'query',
      required: true,
    });
  });

  it('fails closed when a Parameter reference resolves to JSON null', () => {
    const result = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            get: operation({
              parameters: [
                object({ $ref: '#/components/parameters/Broken' }),
              ],
            }),
          }),
        }),
        {
          components: object({
            parameters: object({
              Broken: null,
            }),
          }),
        },
      ),
      '3.2',
    );

    const candidate = result.candidates[0];
    expect(candidate?.parameters).toEqual([]);
    expect(candidate?.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'invalid_openapi_document',
    );
  });

  it('does not flatten parameters into suggested Action input', () => {
    const candidate = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            get: operation({
              parameters: [
                object({ name: 'q', in: 'query' }),
              ],
            }),
          }),
        }),
      ),
      '3.1',
    ).candidates[0];

    expect(candidate?.parameters).toHaveLength(1);
    expect(candidate?.suggestedInputSchema).toBeUndefined();
    expect(candidate?.unresolvedFields).toContain('inputSchema');
  });
});

describe('effective security evidence', () => {
  it('records no security evidence when neither level declares security', () => {
    const security = selectRootPathOperations(
      document('3.1.1', object({ '/items': object({ get: operation() }) })),
      '3.1',
    ).candidates[0]?.security;

    expect(security).toEqual({
      source: 'none',
      requirements: [],
      anonymousAlternative: false,
      inheritedSecurityRemoved: false,
    });
  });

  it('inherits top-level security when operation security is absent', () => {
    const security = selectRootPathOperations(
      document(
        '3.1.1',
        object({ '/items': object({ get: operation() }) }),
        {
          security: [
            object({ OAuth: ['read:items'] }),
          ],
        },
      ),
      '3.1',
    ).candidates[0]?.security;

    expect(security).toEqual({
      source: 'inherited',
      requirements: [
        {
          anonymous: false,
          schemes: [{ scheme: 'OAuth', scopes: ['read:items'] }],
        },
      ],
      anonymousAlternative: false,
      inheritedSecurityRemoved: false,
    });
  });

  it('treats operation security: [] as explicit inherited-security removal', () => {
    const security = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            get: operation({ security: [] }),
          }),
        }),
        {
          security: [
            object({ OAuth: ['read:items'] }),
          ],
        },
      ),
      '3.1',
    ).candidates[0]?.security;

    expect(security).toEqual({
      source: 'operation_override',
      requirements: [],
      anonymousAlternative: false,
      inheritedSecurityRemoved: true,
    });
  });

  it('distinguishes [{}] as an anonymous alternative', () => {
    const security = selectRootPathOperations(
      document(
        '3.1.1',
        object({
          '/items': object({
            get: operation({
              security: [
                object(),
                object({ OAuth: ['read:items'] }),
              ],
            }),
          }),
        }),
      ),
      '3.1',
    ).candidates[0]?.security;

    expect(security?.source).toBe('operation_override');
    expect(security?.anonymousAlternative).toBe(true);
    expect(security?.inheritedSecurityRemoved).toBe(false);
    expect(security?.requirements[0]).toEqual({
      anonymous: true,
      schemes: [],
    });
  });

  it('preserves OR alternatives and AND schemes structurally without granting authority', () => {
    const candidate = selectRootPathOperations(
      document(
        '3.2.1',
        object({
          '/items': object({
            get: operation({
              security: [
                object({
                  ApiKey: [],
                  OAuth: ['read:items', 'tenant:blue'],
                }),
                object({ MutualTls: [] }),
              ],
            }),
          }),
        }),
      ),
      '3.2',
    ).candidates[0];

    expect(candidate?.security.requirements).toEqual([
      {
        anonymous: false,
        schemes: [
          { scheme: 'ApiKey', scopes: [] },
          { scheme: 'OAuth', scopes: ['read:items', 'tenant:blue'] },
        ],
      },
      {
        anonymous: false,
        schemes: [
          { scheme: 'MutualTls', scopes: [] },
        ],
      },
    ]);

    const raw = candidate as unknown as Record<string, unknown>;
    expect(raw).not.toHaveProperty('contextRequirements');
    expect(raw).not.toHaveProperty('authorization');
  });
});
