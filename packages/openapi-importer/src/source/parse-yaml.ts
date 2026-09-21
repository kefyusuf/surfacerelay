import {
  isAlias,
  isMap,
  isNode,
  isScalar,
  isSeq,
  parseDocument,
  type Node,
} from 'yaml';

import {
  blockingDiagnostic,
  type ImportDiagnosticCode,
} from '../diagnostics.js';
import type { JsonObject, JsonValue } from '../json-value.js';
import {
  MAX_DOCUMENT_DEPTH,
  MAX_DOCUMENT_NODES,
} from '../limits.js';
import type { ParsedOpenApiSource } from './parse-source.js';

class YamlConversionError extends Error {
  constructor(
    public readonly code: ImportDiagnosticCode,
    message: string,
  ) {
    super(message);
    this.name = 'YamlConversionError';
  }
}

interface WorkItem {
  node: Node | null;
  depth: number;
  parent: JsonObject | JsonValue[] | null;
  key: string | number | null;
}

function nodeHasExplicitTag(node: Node): boolean {
  return typeof node.tag === 'string' && node.tag.length > 0;
}

function assignValue(
  work: WorkItem,
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

function convertYamlNode(rootNode: Node | null): JsonValue {
  let root: JsonValue | undefined;
  let nodeCount = 0;
  const pending: WorkItem[] = [{
    node: rootNode,
    depth: 1,
    parent: null,
    key: null,
  }];

  const consumeNode = (depth: number): void => {
    if (depth > MAX_DOCUMENT_DEPTH) {
      throw new YamlConversionError(
        'document_limit_exceeded',
        `Document depth exceeds ${MAX_DOCUMENT_DEPTH}.`,
      );
    }

    nodeCount += 1;
    if (nodeCount > MAX_DOCUMENT_NODES) {
      throw new YamlConversionError(
        'document_limit_exceeded',
        `Document node count exceeds ${MAX_DOCUMENT_NODES}.`,
      );
    }
  };

  while (pending.length > 0) {
    const work = pending.pop();
    if (work === undefined) {
      break;
    }

    consumeNode(work.depth);
    const node = work.node;

    if (node === null) {
      assignValue(work, null, (next) => {
        root = next;
      });
      continue;
    }

    if (isAlias(node)) {
      throw new YamlConversionError(
        'yaml_alias_unsupported',
        'YAML aliases are unsupported.',
      );
    }

    if (nodeHasExplicitTag(node)) {
      throw new YamlConversionError(
        'yaml_tag_unsupported',
        `Explicit YAML tag "${node.tag}" is unsupported.`,
      );
    }

    if (isScalar(node)) {
      const value = node.value;

      if (
        value === null ||
        typeof value === 'string' ||
        typeof value === 'boolean'
      ) {
        assignValue(work, value, (next) => {
          root = next;
        });
        continue;
      }

      if (typeof value === 'number' && Number.isFinite(value)) {
        assignValue(work, value, (next) => {
          root = next;
        });
        continue;
      }

      throw new YamlConversionError(
        'invalid_yaml',
        'YAML scalar cannot be represented as a JSON-compatible value.',
      );
    }

    if (isSeq(node)) {
      const target: JsonValue[] = new Array(node.items.length);
      assignValue(work, target, (next) => {
        root = next;
      });

      for (let index = node.items.length - 1; index >= 0; index -= 1) {
        const itemNode = node.items[index] ?? null;
        if (itemNode !== null && !isNode(itemNode)) {
          throw new YamlConversionError(
            'invalid_yaml',
            'YAML sequence item is not a supported parsed node.',
          );
        }

        pending.push({
          node: itemNode,
          depth: work.depth + 1,
          parent: target,
          key: index,
        });
      }
      continue;
    }

    if (isMap(node)) {
      const target = Object.create(null) as JsonObject;
      assignValue(work, target, (next) => {
        root = next;
      });

      const seenKeys = new Set<string>();

      for (let index = node.items.length - 1; index >= 0; index -= 1) {
        const pair = node.items[index];
        if (pair === undefined) {
          continue;
        }

        consumeNode(work.depth + 1); // count mapping key
        const keyNode = pair.key;

        if (isAlias(keyNode)) {
          throw new YamlConversionError(
            'yaml_alias_unsupported',
            'YAML aliases are unsupported, including mapping keys.',
          );
        }

        if (!isScalar(keyNode) || typeof keyNode.value !== 'string') {
          throw new YamlConversionError(
            'invalid_yaml',
            'YAML mapping keys must resolve to strings.',
          );
        }

        if (nodeHasExplicitTag(keyNode)) {
          throw new YamlConversionError(
            'yaml_tag_unsupported',
            `Explicit YAML tag "${keyNode.tag}" is unsupported.`,
          );
        }

        if (seenKeys.has(keyNode.value)) {
          throw new YamlConversionError(
            'invalid_yaml',
            `YAML mapping key "${keyNode.value}" is duplicated.`,
          );
        }
        seenKeys.add(keyNode.value);

        const valueNode = pair.value;
        if (valueNode !== null && !isNode(valueNode)) {
          throw new YamlConversionError(
            'invalid_yaml',
            'YAML mapping value is not a supported parsed node.',
          );
        }

        pending.push({
          node: valueNode,
          depth: work.depth + 1,
          parent: target,
          key: keyNode.value,
        });
      }
      continue;
    }

    throw new YamlConversionError(
      'invalid_yaml',
      'Unsupported YAML AST node.',
    );
  }

  if (root === undefined) {
    throw new YamlConversionError(
      'invalid_yaml',
      'YAML document has no root value.',
    );
  }

  return root;
}

export function parseYamlSource(content: string): ParsedOpenApiSource {
  const document = parseDocument(content, {
    version: '1.2',
    schema: 'core',
    merge: false,
    uniqueKeys: true,
    strict: true,
    resolveKnownTags: false,
    customTags: [],
    prettyErrors: false,
  });

  const tagWarning = document.warnings.find((warning) =>
    warning.code.includes('TAG'),
  );
  if (tagWarning !== undefined) {
    return {
      document: null,
      diagnostics: [
        blockingDiagnostic(
          'yaml_tag_unsupported',
          'Explicit or custom YAML tags are unsupported.',
        ),
      ],
    };
  }

  if (document.errors.length > 0 || document.warnings.length > 0) {
    return {
      document: null,
      diagnostics: [
        blockingDiagnostic(
          'invalid_yaml',
          'Source is not valid supported YAML.',
        ),
      ],
    };
  }

  try {
    const normalized = convertYamlNode(document.contents);

    if (
      normalized === null ||
      typeof normalized !== 'object' ||
      Array.isArray(normalized)
    ) {
      return {
        document: null,
        diagnostics: [
          blockingDiagnostic(
            'invalid_openapi_document',
            'OpenAPI source root must be a mapping object.',
          ),
        ],
      };
    }

    return { document: normalized, diagnostics: [] };
  } catch (error) {
    if (error instanceof YamlConversionError) {
      return {
        document: null,
        diagnostics: [blockingDiagnostic(error.code, error.message)],
      };
    }

    throw error;
  }
}
