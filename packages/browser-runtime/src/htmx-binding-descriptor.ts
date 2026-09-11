import type { RuntimeBinding } from './types.js';

const HTMX_TARGET_KEYS = [
  'inputNames',
  'method',
  'path',
  'requiredInputNames',
  'sourceId',
] as const;

export type HtmxRequestMethod = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface HtmxBindingTarget {
  readonly sourceId: string;
  readonly method: HtmxRequestMethod;
  readonly path: string;
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

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function hasExactTargetKeys(target: Record<string, unknown>): boolean {
  const keys = Object.keys(target).sort();
  return keys.length === HTMX_TARGET_KEYS.length
    && keys.every((key, index) => key === HTMX_TARGET_KEYS[index]);
}

export function parseHtmxBindingTarget(binding: RuntimeBinding): HtmxBindingTarget {
  if (
    binding.driver !== 'htmx'
    || binding.lifecycle !== 'page'
    || !isRecord(binding.target)
    || !hasExactTargetKeys(binding.target)
  ) {
    throw new HtmxBindingDescriptorError(
      'runtime_binding_invalid',
      'HTMX runtime binding is invalid.',
    );
  }

  return {
    sourceId: binding.target.sourceId as string,
    method: binding.target.method as HtmxRequestMethod,
    path: binding.target.path as string,
    inputNames: binding.target.inputNames as readonly string[],
    requiredInputNames: binding.target.requiredInputNames as readonly string[],
  };
}
