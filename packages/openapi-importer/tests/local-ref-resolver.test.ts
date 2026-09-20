import { describe, expect, it } from 'vitest';

import {
  MAX_REF_HOPS,
  MAX_UNIQUE_REF_TARGETS,
} from '../src/limits.js';
import type { JsonObject, JsonValue } from '../src/json-value.js';
import {
  LocalRefTraversalBudget,
  resolveLocalPointer,
} from '../src/refs/local-ref-resolver.js';
import { parseOpenApiFamily } from '../src/source/openapi-version.js';

function object(entries: Record<string, JsonValue> = {}): JsonObject {
  return Object.assign(Object.create(null) as JsonObject, entries);
}

function expectRefCode(
  result: ReturnType<typeof resolveLocalPointer>,
  code: string,
): void {
  expect(result.value).toBeNull();
  expect(result.pointer).toBeNull();
  expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([code]);
}

describe('OpenAPI version family', () => {
  it.each([
    ['3.1.0', '3.1'],
    ['3.1.1', '3.1'],
    ['3.1.999', '3.1'],
    ['3.2.0', '3.2'],
    ['3.2.1', '3.2'],
    ['3.2.999', '3.2'],
  ] as const)('accepts supported semantic patch family %s', (version, family) => {
    const result = parseOpenApiFamily(object({ openapi: version }));

    expect(result.family).toBe(family);
    expect(result.diagnostics).toEqual([]);
  });

  it.each([
    object(),
    object({ openapi: 3.1 }),
    object({ openapi: '2.0' }),
    object({ openapi: '3.0.4' }),
    object({ openapi: '4.0.0' }),
    object({ openapi: '3.1' }),
    object({ openapi: '3.1.0-beta.1' }),
    object({ openapi: '03.1.0' }),
  ])('rejects unsupported or malformed versions', (document) => {
    const result = parseOpenApiFamily(document);

    expect(result.family).toBeNull();
    expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([
      'unsupported_openapi_version',
    ]);
  });
});

describe('same-document JSON Pointer references', () => {
  it('resolves the root reference without copying it', () => {
    const root = object({ openapi: '3.1.0' });
    const result = resolveLocalPointer(root, '#', new LocalRefTraversalBudget());

    expect(result.value).toBe(root);
    expect(result.pointer).toBe('');
    expect(result.diagnostics).toEqual([]);
  });

  it('resolves nested object references', () => {
    const schema = object({ type: 'string' });
    const root = object({
      components: object({
        schemas: object({ Order: schema }),
      }),
    });

    const result = resolveLocalPointer(
      root,
      '#/components/schemas/Order',
      new LocalRefTraversalBudget(),
    );

    expect(result.value).toBe(schema);
    expect(result.pointer).toBe('/components/schemas/Order');
    expect(result.diagnostics).toEqual([]);
  });

  it('decodes strict ~0 and ~1 tokens', () => {
    const target = object({ ok: true });
    const root = object({
      'a/b': object({
        'm~n': target,
      }),
    });

    const result = resolveLocalPointer(
      root,
      '#/a~1b/m~0n',
      new LocalRefTraversalBudget(),
    );

    expect(result.value).toBe(target);
    expect(result.diagnostics).toEqual([]);
  });

  it('percent-decodes a fragment exactly once before JSON Pointer decoding', () => {
    const once = object({ ok: true });
    const root = object({
      components: object({ schemas: object({ Order: once }) }),
      '%2Fcomponents': object({ schemas: object({ Order: object() }) }),
    });

    const result = resolveLocalPointer(
      root,
      '#%2Fcomponents%2Fschemas%2FOrder',
      new LocalRefTraversalBudget(),
    );

    expect(result.value).toBe(once);
    expect(result.pointer).toBe('/components/schemas/Order');
  });

  it('rejects malformed percent encoding', () => {
    expectRefCode(
      resolveLocalPointer(
        object(),
        '#/bad%ZZ',
        new LocalRefTraversalBudget(),
      ),
      'invalid_json_pointer',
    );
  });

  it('rejects named anchors', () => {
    expectRefCode(
      resolveLocalPointer(
        object(),
        '#namedAnchor',
        new LocalRefTraversalBudget(),
      ),
      'anchor_ref_unsupported',
    );
  });

  it.each([
    'other.yaml#/components/schemas/X',
    'relative-file.yaml',
    'file:///tmp/openapi.yaml#/X',
    'http://example.com/openapi.yaml#/X',
    'https://example.com/openapi.yaml#/X',
  ])('rejects external reference %s', (reference) => {
    expectRefCode(
      resolveLocalPointer(
        object(),
        reference,
        new LocalRefTraversalBudget(),
      ),
      'external_ref_forbidden',
    );
  });

  it('rejects invalid JSON Pointer escapes', () => {
    expectRefCode(
      resolveLocalPointer(
        object({ 'bad~2token': 1 }),
        '#/bad~2token',
        new LocalRefTraversalBudget(),
      ),
      'invalid_json_pointer',
    );
  });

  it('returns a diagnostic when a pointer target is missing', () => {
    expectRefCode(
      resolveLocalPointer(
        object({ components: object() }),
        '#/components/schemas/Missing',
        new LocalRefTraversalBudget(),
      ),
      'invalid_json_pointer',
    );
  });

  it('resolves array indexes using JSON Pointer array syntax', () => {
    const target = object({ ok: true });
    const root = object({ values: [target] });
    const result = resolveLocalPointer(
      root,
      '#/values/0',
      new LocalRefTraversalBudget(),
    );

    expect(result.value).toBe(target);
    expect(result.diagnostics).toEqual([]);
  });

  it('rejects array indexes with leading zeroes', () => {
    expectRefCode(
      resolveLocalPointer(
        object({ values: [object()] }),
        '#/values/00',
        new LocalRefTraversalBudget(),
      ),
      'invalid_json_pointer',
    );
  });

  it('rejects a traversal above the hop budget', () => {
    const entries: Record<string, JsonValue> = {};
    for (let index = 0; index <= MAX_REF_HOPS; index += 1) {
      entries[`p${index}`] = object({ index });
    }

    const root = object(entries);
    const budget = new LocalRefTraversalBudget();

    for (let index = 0; index < MAX_REF_HOPS; index += 1) {
      const result = resolveLocalPointer(root, `#/p${index}`, budget);
      expect(result.diagnostics).toEqual([]);
    }

    expectRefCode(
      resolveLocalPointer(root, `#/p${MAX_REF_HOPS}`, budget),
      'ref_limit_exceeded',
    );
  });

  it('rejects a repeated pointer in the active traversal as a cycle', () => {
    const root = object({ a: object() });
    const budget = new LocalRefTraversalBudget();

    expect(
      resolveLocalPointer(root, '#/a', budget).diagnostics,
    ).toEqual([]);

    expectRefCode(
      resolveLocalPointer(root, '#/a', budget),
      'ref_cycle',
    );
  });

  it('shares the unique-target budget across forked sibling traversals', () => {
    const entries: Record<string, JsonValue> = {};
    for (let index = 0; index <= MAX_UNIQUE_REF_TARGETS; index += 1) {
      entries[`k${index}`] = index;
    }

    const root = object(entries);
    const parentBudget = new LocalRefTraversalBudget();

    for (let index = 0; index < MAX_UNIQUE_REF_TARGETS; index += 1) {
      const result = resolveLocalPointer(
        root,
        `#/k${index}`,
        parentBudget.fork(),
      );
      expect(result.diagnostics).toEqual([]);
    }

    expectRefCode(
      resolveLocalPointer(
        root,
        `#/k${MAX_UNIQUE_REF_TARGETS}`,
        parentBudget.fork(),
      ),
      'ref_limit_exceeded',
    );
  });
});
