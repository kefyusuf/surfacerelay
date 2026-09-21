import {
  blockingDiagnostic,
} from '../diagnostics.js';
import {
  isJsonObject,
  JsonTreeError,
  normalizeJsonValue,
} from '../json-value.js';
import { MAX_DOCUMENT_DEPTH } from '../limits.js';
import type { ParsedOpenApiSource } from './parse-source.js';

class DuplicateJsonKeyError extends Error {
  constructor(public readonly key: string) {
    super(`Duplicate JSON object member: ${key}`);
    this.name = 'DuplicateJsonKeyError';
  }
}

class JsonScanDepthError extends Error {
  constructor() {
    super('JSON structure exceeds the parser depth budget.');
    this.name = 'JsonScanDepthError';
  }
}

class JsonDuplicateKeyScanner {
  private offset = 0;

  private readonly numberPattern =
    /-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/y;

  constructor(private readonly source: string) {}

  scan(): void {
    this.skipWhitespace();
    this.scanValue(1);
    this.skipWhitespace();

    if (this.offset !== this.source.length) {
      throw new SyntaxError('Unexpected trailing JSON content.');
    }
  }

  private scanValue(depth: number): void {
    if (depth > MAX_DOCUMENT_DEPTH) {
      throw new JsonScanDepthError();
    }

    this.skipWhitespace();
    const char = this.source[this.offset];

    if (char === '{') {
      this.scanObject(depth);
      return;
    }
    if (char === '[') {
      this.scanArray(depth);
      return;
    }
    if (char === '"') {
      this.scanString();
      return;
    }
    if (char === 't') {
      this.consumeLiteral('true');
      return;
    }
    if (char === 'f') {
      this.consumeLiteral('false');
      return;
    }
    if (char === 'n') {
      this.consumeLiteral('null');
      return;
    }

    this.scanNumber();
  }

  private scanObject(depth: number): void {
    this.expect('{');
    this.skipWhitespace();

    if (this.peek('}')) {
      this.offset += 1;
      return;
    }

    const keys = new Set<string>();

    while (true) {
      this.skipWhitespace();
      const key = this.scanString();

      if (keys.has(key)) {
        throw new DuplicateJsonKeyError(key);
      }
      keys.add(key);

      this.skipWhitespace();
      this.expect(':');
      this.scanValue(depth + 1);
      this.skipWhitespace();

      if (this.peek('}')) {
        this.offset += 1;
        return;
      }

      this.expect(',');
    }
  }

  private scanArray(depth: number): void {
    this.expect('[');
    this.skipWhitespace();

    if (this.peek(']')) {
      this.offset += 1;
      return;
    }

    while (true) {
      this.scanValue(depth + 1);
      this.skipWhitespace();

      if (this.peek(']')) {
        this.offset += 1;
        return;
      }

      this.expect(',');
    }
  }

  private scanString(): string {
    if (!this.peek('"')) {
      throw new SyntaxError('Expected JSON string.');
    }

    const start = this.offset;
    this.offset += 1;

    while (this.offset < this.source.length) {
      const char = this.source[this.offset];

      if (char === '"') {
        this.offset += 1;
        const token = this.source.slice(start, this.offset);
        return JSON.parse(token) as string;
      }

      if (char === '\\') {
        this.offset += 1;
        const escape = this.source[this.offset];

        if (escape === 'u') {
          const digits = this.source.slice(this.offset + 1, this.offset + 5);
          if (!/^[0-9a-fA-F]{4}$/.test(digits)) {
            throw new SyntaxError('Invalid JSON Unicode escape.');
          }
          this.offset += 5;
          continue;
        }

        if (
          escape === '"' ||
          escape === '\\' ||
          escape === '/' ||
          escape === 'b' ||
          escape === 'f' ||
          escape === 'n' ||
          escape === 'r' ||
          escape === 't'
        ) {
          this.offset += 1;
          continue;
        }

        throw new SyntaxError('Invalid JSON escape.');
      }

      if (char === undefined || char.charCodeAt(0) < 0x20) {
        throw new SyntaxError('Invalid control character in JSON string.');
      }

      this.offset += 1;
    }

    throw new SyntaxError('Unterminated JSON string.');
  }

  private scanNumber(): void {
    this.numberPattern.lastIndex = this.offset;
    const match = this.numberPattern.exec(this.source);

    if (match === null || match[0].length === 0) {
      throw new SyntaxError('Invalid JSON value.');
    }

    this.offset = this.numberPattern.lastIndex;
  }

  private consumeLiteral(literal: string): void {
    if (!this.source.startsWith(literal, this.offset)) {
      throw new SyntaxError(`Invalid JSON literal: expected ${literal}.`);
    }

    this.offset += literal.length;
  }

  private expect(char: string): void {
    this.skipWhitespace();

    if (!this.peek(char)) {
      throw new SyntaxError(`Expected JSON token ${char}.`);
    }

    this.offset += 1;
  }

  private peek(char: string): boolean {
    return this.source[this.offset] === char;
  }

  private skipWhitespace(): void {
    while (
      this.source[this.offset] === ' ' ||
      this.source[this.offset] === '\n' ||
      this.source[this.offset] === '\r' ||
      this.source[this.offset] === '\t'
    ) {
      this.offset += 1;
    }
  }
}

export function parseJsonSource(content: string): ParsedOpenApiSource {
  try {
    new JsonDuplicateKeyScanner(content).scan();
  } catch (error) {
    if (error instanceof DuplicateJsonKeyError) {
      return {
        document: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_json',
            `JSON object member "${error.key}" is duplicated.`,
          ),
        ],
      };
    }

    if (error instanceof JsonScanDepthError) {
      return {
        document: null,
        diagnostics: [
          blockingDiagnostic(
            'document_limit_exceeded',
            `Document depth exceeds ${MAX_DOCUMENT_DEPTH}.`,
          ),
        ],
      };
    }

    // Native JSON.parse remains the syntax/value authority. Scanner syntax
    // failures fall through so the same public diagnostic is used below.
  }

  let parsed: unknown;
  try {
    parsed = JSON.parse(content) as unknown;
  } catch {
    return {
      document: null,
      diagnostics: [
        blockingDiagnostic('invalid_json', 'Source is not valid JSON.'),
      ],
    };
  }

  try {
    const normalized = normalizeJsonValue(parsed);

    if (!isJsonObject(normalized)) {
      return {
        document: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_openapi_document',
            'OpenAPI source root must be an object.',
          ),
        ],
      };
    }

    return { document: normalized, diagnostics: [] };
  } catch (error) {
    if (error instanceof JsonTreeError) {
      return {
        document: null,
        diagnostics: [
          blockingDiagnostic(
            error.kind === 'document_limit_exceeded'
              ? 'document_limit_exceeded'
              : 'invalid_json',
            error.message,
          ),
        ],
      };
    }

    throw error;
  }
}
