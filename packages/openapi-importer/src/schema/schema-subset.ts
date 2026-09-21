import {
  blockingDiagnostic,
  type ImportDiagnostic,
} from '../diagnostics.js';
import {
  isJsonObject,
  type JsonObject,
  type JsonValue,
} from '../json-value.js';
import { MAX_SCHEMA_NODES_PER_FRAGMENT } from '../limits.js';
import {
  LocalRefTraversalBudget,
  resolveLocalPointer,
} from '../refs/local-ref-resolver.js';

export interface SchemaSubsetResult {
  schema: JsonObject | null;
  diagnostics: ImportDiagnostic[];
}

const ALLOWED_TYPES = new Set([
  'null',
  'boolean',
  'object',
  'array',
  'number',
  'string',
  'integer',
]);

const ALLOWED_KEYWORDS = new Set([
  '$ref',
  'type',
  'properties',
  'required',
  'items',
  'additionalProperties',
  'enum',
  'const',
  'minimum',
  'maximum',
  'exclusiveMinimum',
  'exclusiveMaximum',
  'multipleOf',
  'minLength',
  'maxLength',
  'pattern',
  'minItems',
  'maxItems',
  'uniqueItems',
  'minProperties',
  'maxProperties',
]);

interface SchemaCopyState {
  nodes: number;
}

function unsupported(message: string): SchemaSubsetResult {
  return {
    schema: null,
    diagnostics: [
      blockingDiagnostic('schema_keyword_unsupported', message),
    ],
  };
}

function dialectUnsupported(message: string): SchemaSubsetResult {
  return {
    schema: null,
    diagnostics: [
      blockingDiagnostic('unsupported_schema_dialect', message),
    ],
  };
}

function consumeNode(state: SchemaCopyState): ImportDiagnostic | null {
  state.nodes += 1;
  if (state.nodes > MAX_SCHEMA_NODES_PER_FRAGMENT) {
    return blockingDiagnostic(
      'schema_limit_exceeded',
      `Schema fragment exceeds the ${MAX_SCHEMA_NODES_PER_FRAGMENT}-node limit.`,
    );
  }

  return null;
}

function stableLiteralKey(value: JsonValue): string {
  if (value === null || typeof value !== 'object') {
    return JSON.stringify(value);
  }

  if (Array.isArray(value)) {
    return `[${value.map((item) => stableLiteralKey(item)).join(',')}]`;
  }

  return `{${Object.keys(value)
    .sort()
    .map((key) => `${JSON.stringify(key)}:${stableLiteralKey(value[key] as JsonValue)}`)
    .join(',')}}`;
}

function copyLiteral(
  value: JsonValue,
  state: SchemaCopyState,
): { value: JsonValue | null; diagnostic: ImportDiagnostic | null } {
  const limitDiagnostic = consumeNode(state);
  if (limitDiagnostic !== null) {
    return { value: null, diagnostic: limitDiagnostic };
  }

  if (value === null || typeof value === 'string' || typeof value === 'boolean') {
    return { value, diagnostic: null };
  }

  if (typeof value === 'number') {
    if (!Number.isFinite(value)) {
      return {
        value: null,
        diagnostic: blockingDiagnostic(
          'schema_keyword_unsupported',
          'Schema literal numbers must be finite.',
        ),
      };
    }
    return { value, diagnostic: null };
  }

  if (Array.isArray(value)) {
    const target: JsonValue[] = [];
    for (const item of value) {
      const copied = copyLiteral(item, state);
      if (copied.diagnostic !== null || copied.value === null && item !== null) {
        return copied;
      }
      target.push(copied.value);
    }
    return { value: target, diagnostic: null };
  }

  const target = Object.create(null) as JsonObject;
  for (const key of Object.keys(value)) {
    const copied = copyLiteral(value[key] as JsonValue, state);
    if (copied.diagnostic !== null || copied.value === null && value[key] !== null) {
      return copied;
    }
    target[key] = copied.value;
  }
  return { value: target, diagnostic: null };
}

function isNonNegativeInteger(value: JsonValue | undefined): value is number {
  return typeof value === 'number' && Number.isInteger(value) && value >= 0;
}

function isFiniteNumber(value: JsonValue | undefined): value is number {
  return typeof value === 'number' && Number.isFinite(value);
}

function consumeKeywordValue(
  state: SchemaCopyState,
  count = 1,
): ImportDiagnostic | null {
  let diagnostic: ImportDiagnostic | null = null;

  for (let index = 0; index < count; index += 1) {
    diagnostic = consumeNode(state);
    if (diagnostic !== null) {
      return diagnostic;
    }
  }

  return null;
}

function copySchemaObject(
  document: JsonObject,
  schema: JsonObject,
  state: SchemaCopyState,
  budget: LocalRefTraversalBudget,
): SchemaSubsetResult {
  const limitDiagnostic = consumeNode(state);
  if (limitDiagnostic !== null) {
    return { schema: null, diagnostics: [limitDiagnostic] };
  }

  if (Object.prototype.hasOwnProperty.call(schema, '$schema')) {
    return dialectUnsupported(
      'Schema-local $schema is unsupported by the v1 importer.',
    );
  }

  const keys = Object.keys(schema);
  for (const key of keys) {
    if (!ALLOWED_KEYWORDS.has(key)) {
      return unsupported(
        `Schema keyword "${key}" is unsupported by the v1 importer.`,
      );
    }
  }

  if (Object.prototype.hasOwnProperty.call(schema, '$ref')) {
    if (keys.length !== 1) {
      return {
        schema: null,
        diagnostics: [
          blockingDiagnostic(
            'schema_ref_sibling_unsupported',
            'Schema $ref with sibling keywords is unsupported in v1.',
          ),
        ],
      };
    }

    const reference = schema.$ref;
    if (typeof reference !== 'string') {
      return unsupported('Schema $ref must be a string.');
    }

    const refValueLimit = consumeKeywordValue(state);
    if (refValueLimit !== null) {
      return { schema: null, diagnostics: [refValueLimit] };
    }

    const resolved = resolveLocalPointer(document, reference, budget);
    if (resolved.pointer === null) {
      return { schema: null, diagnostics: resolved.diagnostics };
    }

    if (!isJsonObject(resolved.value)) {
      return unsupported('Referenced schema target must be an object.');
    }

    return copySchemaObject(document, resolved.value, state, budget);
  }

  const target = Object.create(null) as JsonObject;

  for (const key of keys) {
    const value = schema[key];

    switch (key) {
      case 'type': {
        if (typeof value === 'string') {
          if (!ALLOWED_TYPES.has(value)) {
            return unsupported(`Unsupported schema type "${value}".`);
          }
          const valueLimit = consumeKeywordValue(state);
          if (valueLimit !== null) {
            return { schema: null, diagnostics: [valueLimit] };
          }
          target.type = value;
          break;
        }

        if (
          Array.isArray(value) &&
          value.length > 0 &&
          value.every((item) => typeof item === 'string' && ALLOWED_TYPES.has(item)) &&
          new Set(value).size === value.length
        ) {
          const valueLimit = consumeKeywordValue(state, value.length + 1);
          if (valueLimit !== null) {
            return { schema: null, diagnostics: [valueLimit] };
          }
          target.type = [...value] as JsonValue[];
          break;
        }

        return unsupported('Schema type must be a supported type or unique non-empty type array.');
      }

      case 'properties': {
        if (!isJsonObject(value)) {
          return unsupported('Schema properties must be an object.');
        }

        const containerLimit = consumeKeywordValue(state);
        if (containerLimit !== null) {
          return { schema: null, diagnostics: [containerLimit] };
        }

        const properties = Object.create(null) as JsonObject;
        for (const propertyName of Object.keys(value)) {
          const propertySchema = value[propertyName];
          if (!isJsonObject(propertySchema)) {
            return unsupported(
              `Property schema "${propertyName}" must be an object.`,
            );
          }

          const copied = copySchemaObject(
            document,
            propertySchema,
            state,
            budget.fork(),
          );
          if (copied.schema === null) {
            return copied;
          }
          properties[propertyName] = copied.schema;
        }
        target.properties = properties;
        break;
      }

      case 'required': {
        if (
          !Array.isArray(value) ||
          !value.every((item) => typeof item === 'string') ||
          new Set(value).size !== value.length
        ) {
          return unsupported('Schema required must be an array of unique strings.');
        }

        const valueLimit = consumeKeywordValue(state, value.length + 1);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }

        target.required = [...value] as JsonValue[];
        break;
      }

      case 'items': {
        if (!isJsonObject(value)) {
          return unsupported('Schema items must be an object in v1.');
        }

        const copied = copySchemaObject(document, value, state, budget.fork());
        if (copied.schema === null) {
          return copied;
        }
        target.items = copied.schema;
        break;
      }

      case 'additionalProperties': {
        if (typeof value === 'boolean') {
          const valueLimit = consumeKeywordValue(state);
          if (valueLimit !== null) {
            return { schema: null, diagnostics: [valueLimit] };
          }
          target.additionalProperties = value;
          break;
        }

        if (!isJsonObject(value)) {
          return unsupported(
            'Schema additionalProperties must be a boolean or schema object.',
          );
        }

        const copied = copySchemaObject(document, value, state, budget.fork());
        if (copied.schema === null) {
          return copied;
        }
        target.additionalProperties = copied.schema;
        break;
      }

      case 'enum': {
        if (!Array.isArray(value) || value.length === 0) {
          return unsupported('Schema enum must be a non-empty array.');
        }

        const keys = value.map((item) => stableLiteralKey(item));
        if (new Set(keys).size !== keys.length) {
          return unsupported('Schema enum values must be unique.');
        }

        const copied = copyLiteral(value, state);
        if (copied.diagnostic !== null || !Array.isArray(copied.value)) {
          return {
            schema: null,
            diagnostics: [
              copied.diagnostic ??
                blockingDiagnostic(
                  'schema_keyword_unsupported',
                  'Schema enum could not be copied safely.',
                ),
            ],
          };
        }
        target.enum = copied.value;
        break;
      }

      case 'const': {
        if (value === undefined) {
          return unsupported('Schema const must contain a JSON value.');
        }

        const copied = copyLiteral(value, state);
        if (copied.diagnostic !== null) {
          return { schema: null, diagnostics: [copied.diagnostic] };
        }
        target.const = copied.value;
        break;
      }

      case 'minimum':
      case 'maximum':
      case 'exclusiveMinimum':
      case 'exclusiveMaximum': {
        if (!isFiniteNumber(value)) {
          return unsupported(`Schema ${key} must be a finite number.`);
        }
        const valueLimit = consumeKeywordValue(state);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }
        target[key] = value;
        break;
      }

      case 'multipleOf': {
        if (!isFiniteNumber(value) || value <= 0) {
          return unsupported('Schema multipleOf must be a positive finite number.');
        }
        const valueLimit = consumeKeywordValue(state);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }
        target.multipleOf = value;
        break;
      }

      case 'minLength':
      case 'maxLength':
      case 'minItems':
      case 'maxItems':
      case 'minProperties':
      case 'maxProperties': {
        if (!isNonNegativeInteger(value)) {
          return unsupported(`Schema ${key} must be a non-negative integer.`);
        }
        const valueLimit = consumeKeywordValue(state);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }
        target[key] = value;
        break;
      }

      case 'pattern': {
        if (typeof value !== 'string') {
          return unsupported('Schema pattern must be a string.');
        }

        try {
          new RegExp(value, 'u');
        } catch {
          return unsupported('Schema pattern must be a valid ECMAScript regular expression.');
        }

        const valueLimit = consumeKeywordValue(state);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }
        target.pattern = value;
        break;
      }

      case 'uniqueItems': {
        if (typeof value !== 'boolean') {
          return unsupported('Schema uniqueItems must be a boolean.');
        }
        const valueLimit = consumeKeywordValue(state);
        if (valueLimit !== null) {
          return { schema: null, diagnostics: [valueLimit] };
        }
        target.uniqueItems = value;
        break;
      }
    }
  }

  return { schema: target, diagnostics: [] };
}

export function copySupportedSchema(
  document: JsonObject,
  schema: JsonObject,
): SchemaSubsetResult {
  if (Object.prototype.hasOwnProperty.call(document, 'jsonSchemaDialect')) {
    return dialectUnsupported(
      'Explicit OpenAPI jsonSchemaDialect is unsupported by the v1 importer.',
    );
  }

  return copySchemaObject(
    document,
    schema,
    { nodes: 0 },
    new LocalRefTraversalBudget(),
  );
}
