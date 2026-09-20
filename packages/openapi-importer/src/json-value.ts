import {
  MAX_DOCUMENT_DEPTH,
  MAX_DOCUMENT_NODES,
} from './limits.js';

export type JsonPrimitive = string | number | boolean | null;
export type JsonValue = JsonPrimitive | JsonValue[] | JsonObject;

export interface JsonObject {
  [key: string]: JsonValue;
}

export type JsonTreeErrorKind = 'invalid_value' | 'document_limit_exceeded';

export class JsonTreeError extends Error {
  constructor(
    public readonly kind: JsonTreeErrorKind,
    message: string,
  ) {
    super(message);
    this.name = 'JsonTreeError';
  }
}

interface WorkItem {
  source: unknown;
  depth: number;
  parent: JsonObject | JsonValue[] | null;
  key: string | number | null;
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

export function normalizeJsonValue(source: unknown): JsonValue {
  let root: JsonValue | undefined;
  let nodeCount = 0;
  const seen = new WeakSet<object>();
  const pending: WorkItem[] = [{
    source,
    depth: 1,
    parent: null,
    key: null,
  }];

  const consumeNode = (depth: number): void => {
    if (depth > MAX_DOCUMENT_DEPTH) {
      throw new JsonTreeError(
        'document_limit_exceeded',
        `Document depth exceeds ${MAX_DOCUMENT_DEPTH}.`,
      );
    }

    nodeCount += 1;
    if (nodeCount > MAX_DOCUMENT_NODES) {
      throw new JsonTreeError(
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
    const value = work.source;

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

    if (typeof value === 'number') {
      if (!Number.isFinite(value)) {
        throw new JsonTreeError(
          'invalid_value',
          'JSON-compatible numbers must be finite.',
        );
      }

      assignValue(work, value, (next) => {
        root = next;
      });
      continue;
    }

    if (Array.isArray(value)) {
      if (seen.has(value)) {
        throw new JsonTreeError(
          'invalid_value',
          'Repeated or cyclic object references are not accepted.',
        );
      }
      seen.add(value);

      const target: JsonValue[] = new Array(value.length);
      assignValue(work, target, (next) => {
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
        throw new JsonTreeError(
          'invalid_value',
          'Repeated or cyclic object references are not accepted.',
        );
      }
      seen.add(object);

      const target = Object.create(null) as JsonObject;
      assignValue(work, target, (next) => {
        root = next;
      });

      const entries = Object.entries(object);
      for (let index = entries.length - 1; index >= 0; index -= 1) {
        const entry = entries[index];
        if (entry === undefined) {
          continue;
        }

        const [key, child] = entry;
        consumeNode(work.depth + 1); // count the mapping key itself
        pending.push({
          source: child,
          depth: work.depth + 1,
          parent: target,
          key,
        });
      }
      continue;
    }

    throw new JsonTreeError(
      'invalid_value',
      `Unsupported JSON value type: ${typeof value}.`,
    );
  }

  if (root === undefined) {
    throw new JsonTreeError('invalid_value', 'Document has no JSON value.');
  }

  return root;
}

export function isJsonObject(value: JsonValue): value is JsonObject {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}
