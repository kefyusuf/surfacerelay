import { Buffer } from 'node:buffer';

import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import type { JsonObject } from '../json-value.js';
import { MAX_SOURCE_BYTES } from '../limits.js';
import { parseJsonSource } from './parse-json.js';
import { parseYamlSource } from './parse-yaml.js';

export type SourceFormat = 'json' | 'yaml';

export interface ParseOpenApiSourceInput {
  format: SourceFormat;
  content: string;
}

export interface ParsedOpenApiSource {
  document: JsonObject | null;
  diagnostics: ImportDiagnostic[];
}

export function parseOpenApiSource(
  input: ParseOpenApiSourceInput,
): ParsedOpenApiSource {
  const sourceBytes = Buffer.byteLength(input.content, 'utf8');

  if (sourceBytes > MAX_SOURCE_BYTES) {
    return {
      document: null,
      diagnostics: [
        blockingDiagnostic(
          'source_too_large',
          `Source exceeds the ${MAX_SOURCE_BYTES}-byte limit.`,
        ),
      ],
    };
  }

  return input.format === 'json'
    ? parseJsonSource(input.content)
    : parseYamlSource(input.content);
}
