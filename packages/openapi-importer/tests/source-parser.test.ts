import { describe, expect, it } from 'vitest';

import {
  MAX_DOCUMENT_DEPTH,
  MAX_DOCUMENT_NODES,
  MAX_SOURCE_BYTES,
} from '../src/limits.js';
import { parseOpenApiSource } from '../src/source/parse-source.js';

function expectOnlyCode(
  result: ReturnType<typeof parseOpenApiSource>,
  code: string,
): void {
  expect(result.document).toBeNull();
  expect(result.diagnostics.map((diagnostic) => diagnostic.code)).toEqual([code]);
}

function nestedJsonObject(depth: number): string {
  let value = '0';

  for (let index = 0; index < depth; index += 1) {
    value = `{"level":${value}}`;
  }

  return value;
}

describe('bounded OpenAPI source parsing', () => {
  it('rejects source content above the 2 MiB byte budget before parsing', () => {
    const content = '{"x":"' + 'a'.repeat(MAX_SOURCE_BYTES) + '"}';
    expectOnlyCode(
      parseOpenApiSource({ format: 'json', content }),
      'source_too_large',
    );
  });

  it('parses JSON objects into null-prototype safe maps', () => {
    const result = parseOpenApiSource({
      format: 'json',
      content: '{"openapi":"3.1.0","nested":{"value":1},"items":[{"ok":true}]}',
    });

    expect(result.diagnostics).toEqual([]);
    expect(result.document).not.toBeNull();
    expect(Object.getPrototypeOf(result.document)).toBeNull();
    expect(Object.getPrototypeOf(result.document?.nested)).toBeNull();

    const items = result.document?.items;
    expect(Array.isArray(items)).toBe(true);
    if (!Array.isArray(items)) {
      throw new Error('items must be an array');
    }
    expect(Object.getPrototypeOf(items[0])).toBeNull();
  });

  it('rejects invalid JSON deterministically', () => {
    expectOnlyCode(
      parseOpenApiSource({ format: 'json', content: '{"openapi":' }),
      'invalid_json',
    );
  });

  it('rejects duplicate JSON object member names, including escaped equivalents', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'json',
        content: '{"openapi":"3.1.0","\\u006fpenapi":"3.2.0"}',
      }),
      'invalid_json',
    );
  });

  it('parses YAML mappings into null-prototype safe maps', () => {
    const result = parseOpenApiSource({
      format: 'yaml',
      content: 'openapi: 3.2.0\ninfo:\n  title: Fixture\npaths: {}\n',
    });

    expect(result.diagnostics).toEqual([]);
    expect(result.document).not.toBeNull();
    expect(Object.getPrototypeOf(result.document)).toBeNull();
    expect(Object.getPrototypeOf(result.document?.info)).toBeNull();
    expect(Object.getPrototypeOf(result.document?.paths)).toBeNull();
  });

  it('rejects YAML aliases before JS conversion', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'yaml',
        content: 'base: &base\n  value: 1\ncopy: *base\n',
      }),
      'yaml_alias_unsupported',
    );
  });

  it('rejects explicit YAML tags before JS conversion', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'yaml',
        content: 'openapi: !!str 3.1.0\npaths: {}\n',
      }),
      'yaml_tag_unsupported',
    );
  });

  it('rejects duplicate YAML keys', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'yaml',
        content: 'openapi: 3.1.0\nopenapi: 3.2.0\n',
      }),
      'invalid_yaml',
    );
  });

  it('rejects multi-document YAML', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'yaml',
        content: 'openapi: 3.1.0\n---\nopenapi: 3.2.0\n',
      }),
      'invalid_yaml',
    );
  });

  it('rejects documents deeper than the depth budget', () => {
    expectOnlyCode(
      parseOpenApiSource({
        format: 'json',
        content: nestedJsonObject(MAX_DOCUMENT_DEPTH + 1),
      }),
      'document_limit_exceeded',
    );
  });

  it('rejects documents above the node-count budget', () => {
    const values = new Array(MAX_DOCUMENT_NODES).fill('0').join(',');
    const content = `{"items":[${values}]}`;

    expectOnlyCode(
      parseOpenApiSource({ format: 'json', content }),
      'document_limit_exceeded',
    );
  });

  it('rejects non-object document roots', () => {
    expectOnlyCode(
      parseOpenApiSource({ format: 'json', content: '[]' }),
      'invalid_openapi_document',
    );
  });
});
