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
  const schema = definition.inputSchema;
  const properties = isRecord(schema.properties) ? schema.properties : {};
  const required = Array.isArray(schema.required) ? schema.required : [];

  return parseHtmxBindingTarget({
    bindingId: 'htmx-producer',
    action: { id: definition.id, version: definition.version },
    driver: 'htmx',
    lifecycle: 'page',
    target: {
      sourceId: options.sourceId,
      method: options.method,
      path: options.path,
      inputNames: Object.keys(properties).sort(),
      requiredInputNames: [...required].sort(),
    },
    expiresAt: null,
  });
}
