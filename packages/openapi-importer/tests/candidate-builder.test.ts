import { readFileSync } from 'node:fs';

import { describe, expect, it } from 'vitest';

import { importOpenApi } from '../src/import/candidate-builder.js';
import { MAX_DIAGNOSTICS } from '../src/limits.js';

function fixture(path: string): string {
  return readFileSync(new URL(path, import.meta.url), 'utf8');
}

describe('deterministic OpenAPI import report', () => {
  it('builds deterministic candidates from OpenAPI 3.1 JSON and enriches safe schemas', () => {
    const result = importOpenApi({
      format: 'json',
      content: fixture('./fixtures/positive/import-minimal-31.json'),
    });

    expect(result.openapiVersion).toBe('3.1.1');
    expect(result.truncatedDiagnostics).toBe(false);
    expect(result.candidates.map((candidate) => [
      candidate.source.pathTemplate,
      candidate.source.httpMethod,
      candidate.source.operationPointer,
    ])).toEqual([
      ['/alpha', 'post', '/paths/~1alpha/post'],
      ['/zeta', 'get', '/paths/~1zeta/get'],
    ]);

    const alpha = result.candidates[0];
    expect(alpha?.source.operationId).toBe('AlphaExact');
    expect(alpha?.suggestedInputSchema).toEqual({
      type: 'object',
      properties: {
        name: { type: 'string' },
      },
      required: ['name'],
      additionalProperties: false,
    });
    expect(alpha?.suggestedOutputSchema).toEqual({
      type: 'object',
      properties: {
        id: { type: 'string' },
      },
      required: ['id'],
      additionalProperties: false,
    });
    expect(alpha?.security).toMatchObject({
      source: 'operation_override',
      anonymousAlternative: false,
      inheritedSecurityRemoved: false,
    });
    expect(alpha?.security.requirements[0]?.schemes[0]).toEqual({
      scheme: 'OAuth',
      scopes: ['write:items'],
    });

    const raw = alpha as unknown as Record<string, unknown>;
    for (const field of ['id', 'version', 'scope', 'effect', 'risk', 'idempotency', 'contextRequirements']) {
      expect(raw).not.toHaveProperty(field);
    }
  });

  it('imports OpenAPI 3.2 YAML QUERY as a source candidate', () => {
    const result = importOpenApi({
      format: 'yaml',
      content: fixture('./fixtures/positive/import-query-32.yaml'),
    });

    expect(result.openapiVersion).toBe('3.2.1');
    expect(result.candidates).toHaveLength(1);
    expect(result.candidates[0]?.source).toMatchObject({
      operationId: 'Search_EXACT',
      httpMethod: 'query',
      pathTemplate: '/search',
      operationPointer: '/paths/~1search/query',
    });
    expect(result.candidates[0]?.suggestedInputSchema).toEqual({
      type: 'object',
      properties: {},
      additionalProperties: false,
    });
    expect(result.candidates[0]?.suggestedOutputSchema).toBeDefined();
  });

  it('stops after unsupported OpenAPI version classification', () => {
    const result = importOpenApi({
      format: 'json',
      content: fixture('./fixtures/negative/import-30.json'),
    });

    expect(result.openapiVersion).toBe('3.0.4');
    expect(result.candidates).toEqual([]);
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'unsupported_openapi_version',
    ]);
  });

  it('reports external refs and Path Item ref+sibling ambiguity without execution fallback', () => {
    const external = importOpenApi({
      format: 'json',
      content: fixture('./fixtures/negative/import-external-ref.json'),
    });
    const sibling = importOpenApi({
      format: 'json',
      content: fixture('./fixtures/negative/import-path-ref-sibling.json'),
    });

    expect(external.candidates).toEqual([]);
    expect(external.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'external_ref_forbidden',
    );
    expect(sibling.candidates).toEqual([]);
    expect(sibling.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'path_item_ref_sibling_ambiguous',
    );
  });

  it('keeps unsupported schema suggestions unresolved', () => {
    const result = importOpenApi({
      format: 'json',
      content: fixture('./fixtures/negative/import-unsupported-schema.json'),
    });

    expect(result.candidates).toHaveLength(1);
    expect(result.candidates[0]?.suggestedInputSchema).toBeUndefined();
    expect(result.candidates[0]?.unresolvedFields).toContain('inputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'schema_keyword_unsupported',
    );
  });

  it('keeps parameterized input unresolved and preserves raw source prose only as evidence', () => {
    const markdown = '<b>unsafe</b> **markdown**';
    const content = JSON.stringify({
      openapi: '3.1.1',
      info: { title: 'Fixture', version: '1' },
      paths: {
        '/find': {
          get: {
            description: markdown,
            parameters: [{ name: 'q', in: 'query' }],
            responses: { '204': { description: 'none' } },
          },
        },
      },
    });

    const result = importOpenApi({ format: 'json', content });
    const candidate = result.candidates[0];

    expect(candidate?.sourceDescription).toBe(markdown);
    expect(candidate?.suggestedInputSchema).toBeUndefined();
    expect(candidate?.unresolvedFields).toContain('inputSchema');
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toContain(
      'ambiguous_input_mapping',
    );
  });

  it('leaves multiple request media and multiple success outputs unresolved', () => {
    const content = JSON.stringify({
      openapi: '3.1.1',
      info: { title: 'Fixture', version: '1' },
      paths: {
        '/items': {
          post: {
            requestBody: {
              content: {
                'application/json': { schema: { type: 'object' } },
                'application/xml': { schema: { type: 'object' } },
              },
            },
            responses: {
              '200': { description: 'ok' },
              '201': { description: 'created' },
            },
          },
        },
      },
    });

    const result = importOpenApi({ format: 'json', content });

    expect(result.candidates[0]?.suggestedInputSchema).toBeUndefined();
    expect(result.candidates[0]?.suggestedOutputSchema).toBeUndefined();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual(
      expect.arrayContaining([
        'ambiguous_input_mapping',
        'ambiguous_success_output',
      ]),
    );
  });

  it('sorts candidates independently from OpenAPI source insertion order', () => {
    const contentA = JSON.stringify({
      openapi: '3.2.1',
      info: { title: 'Fixture', version: '1' },
      paths: {
        '/b': {
          post: { responses: { '204': { description: 'none' } } },
          get: { responses: { '204': { description: 'none' } } },
        },
        '/a': {
          query: { responses: { '204': { description: 'none' } } },
          delete: { responses: { '204': { description: 'none' } } },
        },
      },
    });
    const contentB = JSON.stringify({
      openapi: '3.2.1',
      info: { title: 'Fixture', version: '1' },
      paths: {
        '/a': {
          delete: { responses: { '204': { description: 'none' } } },
          query: { responses: { '204': { description: 'none' } } },
        },
        '/b': {
          get: { responses: { '204': { description: 'none' } } },
          post: { responses: { '204': { description: 'none' } } },
        },
      },
    });

    const keys = (content: string) =>
      importOpenApi({ format: 'json', content }).candidates.map((candidate) =>
        `${candidate.source.pathTemplate}|${candidate.source.httpMethod}|${candidate.source.operationPointer}`,
      );

    expect(keys(contentA)).toEqual(keys(contentB));
    expect(keys(contentA)).toEqual([
      '/a|delete|/paths/~1a/delete',
      '/a|query|/paths/~1a/query',
      '/b|get|/paths/~1b/get',
      '/b|post|/paths/~1b/post',
    ]);
  });

  it('orders report diagnostics by source pointer then code while preserving duplicates', () => {
    const oversized = 'x'.repeat(8_193);
    const content = JSON.stringify({
      openapi: '3.1.1',
      info: { title: 'Fixture', version: '1' },
      paths: {
        '/b': {
          get: {
            summary: oversized,
            description: oversized,
            parameters: [{ name: 'q', in: 'query' }],
            responses: { '204': { description: 'none' } },
          },
        },
        '/a': {
          get: {
            summary: oversized,
            responses: { '204': { description: 'none' } },
          },
        },
      },
    });

    const result = importOpenApi({ format: 'json', content });
    const diagnostics = result.diagnostics.filter(
      (diagnostic) => diagnostic.code === 'source_text_too_large',
    );

    expect(diagnostics).toHaveLength(3);
    expect(diagnostics.map((diagnostic) => diagnostic.sourcePointer)).toEqual([
      '/paths/~1a/get',
      '/paths/~1b/get',
      '/paths/~1b/get',
    ]);
  });

  it('reserves the 500th final diagnostic slot for truncation evidence', () => {
    const build = (count: number) => {
      const paths: Record<string, unknown> = {};
      for (let index = 0; index < count; index += 1) {
        paths[`/exact-${String(index).padStart(4, '0')}`] = {
          get: 'invalid',
        };
      }

      return importOpenApi({
        format: 'json',
        content: JSON.stringify({
          openapi: '3.1.1',
          info: { title: 'Fixture', version: '1' },
          paths,
        }),
      });
    };

    const belowLimit = build(MAX_DIAGNOSTICS - 1);
    expect(belowLimit.truncatedDiagnostics).toBe(false);
    expect(belowLimit.diagnostics).toHaveLength(MAX_DIAGNOSTICS - 1);
    expect(
      belowLimit.diagnostics.some(
        (diagnostic) => diagnostic.code === 'diagnostic_limit_reached',
      ),
    ).toBe(false);

    const boundary = build(MAX_DIAGNOSTICS);
    expect(boundary.truncatedDiagnostics).toBe(true);
    expect(boundary.diagnostics).toHaveLength(MAX_DIAGNOSTICS);
    expect(boundary.diagnostics.at(-1)?.code).toBe(
      'diagnostic_limit_reached',
    );
    expect(
      boundary.diagnostics.filter(
        (diagnostic) => diagnostic.code === 'invalid_openapi_document',
      ),
    ).toHaveLength(MAX_DIAGNOSTICS - 1);
  });

  it('caps final report diagnostics at 500 including exactly one terminal truncation diagnostic', () => {
    const paths: Record<string, unknown> = {};
    for (let index = 0; index < 520; index += 1) {
      paths[`/bad-${String(index).padStart(4, '0')}`] = { get: 'invalid' };
    }

    const result = importOpenApi({
      format: 'json',
      content: JSON.stringify({
        openapi: '3.1.1',
        info: { title: 'Fixture', version: '1' },
        paths,
      }),
    });

    expect(result.truncatedDiagnostics).toBe(true);
    expect(result.diagnostics).toHaveLength(MAX_DIAGNOSTICS);
    expect(result.diagnostics.at(-1)?.code).toBe('diagnostic_limit_reached');
    expect(
      result.diagnostics.filter(
        (diagnostic) => diagnostic.code === 'diagnostic_limit_reached',
      ),
    ).toHaveLength(1);
  });
});
