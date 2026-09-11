import type { ActionDefinition, RuntimeBinding } from './types.js';

const HTMX_TARGET_KEYS = [
  'inputNames',
  'method',
  'path',
  'requiredInputNames',
  'sourceId',
] as const;

const SOURCE_ID_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{0,239}$/;
const ASCII_CONTROL_PATTERN = /[\u0000-\u001F\u007F]/;
const UNSUPPORTED_TOP_LEVEL_MAPPING_KEYWORDS = [
  '$ref',
  '$dynamicRef',
  'allOf',
  'anyOf',
  'oneOf',
  'not',
  'if',
  'then',
  'else',
  'patternProperties',
  'unevaluatedProperties',
  'dependentSchemas',
] as const;

export type HtmxRequestMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

const HTMX_METHODS = new Set<HtmxRequestMethod>(['GET', 'POST', 'PUT', 'PATCH', 'DELETE']);

export interface HtmxBindingTarget {
  readonly sourceId: string;
  readonly method: HtmxRequestMethod;
  readonly path: string;
  readonly inputNames: readonly string[];
  readonly requiredInputNames: readonly string[];
}

export interface CreateHtmxBindingTargetOptions {
  readonly sourceId: string;
  readonly method: HtmxRequestMethod;
  readonly path: string;
}

interface DerivedInputMapping {
  readonly inputNames: readonly string[];
  readonly requiredInputNames: readonly string[];
}

export type HtmxBindingDescriptorErrorCode =
  | 'runtime_binding_invalid'
  | 'source_id_invalid'
  | 'method_invalid'
  | 'path_invalid'
  | 'input_mapping_invalid'
  | 'input_schema_unsupported';

export class HtmxBindingDescriptorError extends Error {
  constructor(
    public readonly code: HtmxBindingDescriptorErrorCode,
    message: string,
  ) {
    super(message);
    this.name = 'HtmxBindingDescriptorError';
  }
}

function descriptorError(
  code: HtmxBindingDescriptorErrorCode,
  message: string,
): HtmxBindingDescriptorError {
  return new HtmxBindingDescriptorError(code, message);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function hasOwn(value: Record<string, unknown>, key: string): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function hasExactTargetKeys(target: Record<string, unknown>): boolean {
  const keys = Object.keys(target).sort();
  return keys.length === HTMX_TARGET_KEYS.length
    && keys.every((key, index) => key === HTMX_TARGET_KEYS[index]);
}

function parseSourceId(value: unknown): string {
  if (typeof value !== 'string' || !SOURCE_ID_PATTERN.test(value)) {
    throw descriptorError('source_id_invalid', 'HTMX sourceId is invalid.');
  }
  return value;
}

function parseMethod(value: unknown): HtmxRequestMethod {
  if (typeof value !== 'string' || !HTMX_METHODS.has(value as HtmxRequestMethod)) {
    throw descriptorError('method_invalid', 'HTMX request method is unsupported.');
  }
  return value as HtmxRequestMethod;
}

function parsePath(value: unknown): string {
  if (
    typeof value !== 'string'
    || value.length < 1
    || value.length > 2048
    || !value.startsWith('/')
    || value.startsWith('//')
    || value.includes('#')
    || value.includes('\\')
    || ASCII_CONTROL_PATTERN.test(value)
  ) {
    throw descriptorError('path_invalid', 'HTMX request path is invalid.');
  }
  return value;
}

function parseNameList(value: unknown, label: string): readonly string[] {
  if (!Array.isArray(value)) {
    throw descriptorError('input_mapping_invalid', `${label} must be an array.`);
  }

  const seen = new Set<string>();
  const copy: string[] = [];
  for (const entry of value) {
    if (typeof entry !== 'string' || entry.length === 0 || seen.has(entry)) {
      throw descriptorError('input_mapping_invalid', `${label} contains an invalid entry.`);
    }
    seen.add(entry);
    copy.push(entry);
  }

  return Object.freeze(copy);
}

function deriveInputMapping(inputSchema: unknown): DerivedInputMapping {
  if (!isRecord(inputSchema)) {
    throw descriptorError('input_schema_unsupported', 'HTMX Action input schema must be an object schema.');
  }
  if (inputSchema.type !== 'object' || inputSchema.additionalProperties !== false) {
    throw descriptorError(
      'input_schema_unsupported',
      'HTMX Action input schema must be a closed top-level object.',
    );
  }

  for (const keyword of UNSUPPORTED_TOP_LEVEL_MAPPING_KEYWORDS) {
    if (hasOwn(inputSchema, keyword)) {
      throw descriptorError(
        'input_schema_unsupported',
        `HTMX Action input schema uses unsupported top-level keyword "${keyword}".`,
      );
    }
  }

  const rawProperties = inputSchema.properties;
  if (rawProperties !== undefined && !isRecord(rawProperties)) {
    throw descriptorError('input_schema_unsupported', 'HTMX Action input schema properties are unsupported.');
  }
  const properties = rawProperties === undefined ? {} : rawProperties;
  const inputNames = Object.keys(properties);
  if (inputNames.some((name) => name.length === 0)) {
    throw descriptorError('input_schema_unsupported', 'HTMX Action input schema has an empty property name.');
  }

  const rawRequired = inputSchema.required;
  if (rawRequired !== undefined && !Array.isArray(rawRequired)) {
    throw descriptorError('input_schema_unsupported', 'HTMX Action input schema required list is unsupported.');
  }
  const required = rawRequired === undefined ? [] : rawRequired;
  const seen = new Set<string>();
  for (const name of required) {
    if (
      typeof name !== 'string'
      || name.length === 0
      || seen.has(name)
      || !hasOwn(properties, name)
    ) {
      throw descriptorError('input_schema_unsupported', 'HTMX Action input schema required list is invalid.');
    }
    seen.add(name);
  }

  return {
    inputNames: Object.freeze([...inputNames].sort()),
    requiredInputNames: Object.freeze([...required].sort() as string[]),
  };
}

export function parseHtmxBindingTarget(binding: RuntimeBinding): HtmxBindingTarget {
  if (
    binding.driver !== 'htmx'
    || binding.lifecycle !== 'page'
    || !isRecord(binding.target)
    || !hasExactTargetKeys(binding.target)
  ) {
    throw descriptorError('runtime_binding_invalid', 'HTMX runtime binding is invalid.');
  }

  const inputNames = parseNameList(binding.target.inputNames, 'HTMX inputNames');
  const requiredInputNames = parseNameList(
    binding.target.requiredInputNames,
    'HTMX requiredInputNames',
  );
  const allowed = new Set(inputNames);
  if (requiredInputNames.some((name) => !allowed.has(name))) {
    throw descriptorError(
      'input_mapping_invalid',
      'HTMX requiredInputNames must be a subset of inputNames.',
    );
  }

  return Object.freeze({
    sourceId: parseSourceId(binding.target.sourceId),
    method: parseMethod(binding.target.method),
    path: parsePath(binding.target.path),
    inputNames,
    requiredInputNames,
  });
}

export function createHtmxBindingTarget(
  definition: ActionDefinition,
  options: CreateHtmxBindingTargetOptions,
): HtmxBindingTarget {
  const mapping = deriveInputMapping(definition.inputSchema);

  return parseHtmxBindingTarget({
    bindingId: 'htmx-producer',
    action: { id: definition.id, version: definition.version },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: options.sourceId,
      method: options.method,
      path: options.path,
      inputNames: mapping.inputNames,
      requiredInputNames: mapping.requiredInputNames,
    },
    expiresAt: null,
  });
}
