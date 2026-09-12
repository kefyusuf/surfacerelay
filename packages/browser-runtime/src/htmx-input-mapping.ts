import type { HtmxBindingTarget } from './htmx-binding-descriptor.js';
import { HtmxBindingExecutionError } from './htmx-errors.js';

const HTMX_OBJECT_VALUES_RESERVED_INPUT_NAMES = new Set(['hasOwnProperty']);

function mappingError(message: string): HtmxBindingExecutionError {
  return new HtmxBindingExecutionError('binding_input_unmappable', message);
}

function hasOwn(value: object, key: PropertyKey): boolean {
  return Object.prototype.hasOwnProperty.call(value, key);
}

function assertDataProperty(owner: object, key: string): unknown {
  const descriptor = Object.getOwnPropertyDescriptor(owner, key);
  if (!descriptor || !descriptor.enumerable || !('value' in descriptor)) {
    throw mappingError(
      `HTMX Action value property "${key}" is not a plain enumerable data property.`,
    );
  }
  return descriptor.value;
}

function assertJsonData(value: unknown, stack: Set<object>): void {
  if (value === null) return;

  switch (typeof value) {
    case 'string':
    case 'boolean':
      return;
    case 'number':
      if (!Number.isFinite(value)) {
        throw mappingError('HTMX Action numbers must be finite.');
      }
      return;
    case 'undefined':
    case 'bigint':
    case 'symbol':
    case 'function':
      throw mappingError('HTMX Action value is outside the supported JSON-data domain.');
    case 'object':
      break;
    default:
      throw mappingError('HTMX Action value is unsupported.');
  }

  const objectValue = value as object;
  if (stack.has(objectValue)) {
    throw mappingError('HTMX Action value contains a cycle.');
  }
  stack.add(objectValue);

  try {
    if (Array.isArray(value)) {
      const allowedKeys = new Set<string>([
        ...Array.from({ length: value.length }, (_entry, index) => String(index)),
        'length',
      ]);
      const ownKeys = Reflect.ownKeys(value);
      if (
        ownKeys.some((key) => typeof key !== 'string' || !allowedKeys.has(key))
        || ownKeys.length !== allowedKeys.size
      ) {
        throw mappingError(
          'HTMX Action arrays must be dense JSON arrays without extra properties.',
        );
      }

      for (let index = 0; index < value.length; index += 1) {
        const key = String(index);
        if (!hasOwn(value, key)) {
          throw mappingError('HTMX Action arrays must not contain sparse holes.');
        }
        assertJsonData(assertDataProperty(value, key), stack);
      }
      return;
    }

    const prototype = Object.getPrototypeOf(value);
    if (prototype !== Object.prototype && prototype !== null) {
      throw mappingError('HTMX Action objects must be plain objects.');
    }

    for (const key of Reflect.ownKeys(value)) {
      if (typeof key !== 'string') {
        throw mappingError('HTMX Action objects must not contain symbol keys.');
      }
      assertJsonData(assertDataProperty(value, key), stack);
    }
  } finally {
    stack.delete(objectValue);
  }
}

function encodeJsonString(value: string): string {
  const encoded = JSON.stringify(value);
  if (typeof encoded !== 'string') {
    throw mappingError('HTMX Action string could not be encoded deterministically.');
  }
  return encoded;
}

function encodeValidatedJson(value: unknown): string {
  if (value === null) return 'null';

  switch (typeof value) {
    case 'string':
      return encodeJsonString(value);
    case 'boolean':
      return value ? 'true' : 'false';
    case 'number':
      return String(value);
    case 'object':
      break;
    default:
      throw mappingError('Validated HTMX Action value left the JSON-data domain.');
  }

  if (Array.isArray(value)) {
    const encodedItems: string[] = [];
    for (let index = 0; index < value.length; index += 1) {
      encodedItems.push(encodeValidatedJson(assertDataProperty(value, String(index))));
    }
    return `[${encodedItems.join(',')}]`;
  }

  const encodedEntries: string[] = [];
  for (const key of Reflect.ownKeys(value)) {
    if (typeof key !== 'string') {
      throw mappingError('Validated HTMX Action object contains a symbol key.');
    }
    encodedEntries.push(
      `${encodeJsonString(key)}:${encodeValidatedJson(assertDataProperty(value, key))}`,
    );
  }
  return `{${encodedEntries.join(',')}}`;
}

function encodeActionValue(value: unknown): string {
  assertJsonData(value, new Set<object>());

  if (typeof value === 'string') return value;
  if (value === null) return 'null';
  if (typeof value === 'number' || typeof value === 'boolean') return String(value);

  return encodeValidatedJson(value);
}

export function mapHtmxActionInput(
  target: HtmxBindingTarget,
  input: Record<string, unknown>,
): Readonly<Record<string, string>> {
  const allowed = new Set(target.inputNames);
  const mapped: Record<string, string> = {};

  for (const key of Reflect.ownKeys(input)) {
    if (typeof key !== 'string') {
      throw mappingError('HTMX Action input must not contain symbol keys.');
    }
    if (!allowed.has(key)) {
      throw mappingError(`HTMX Action input contains unknown key "${key}".`);
    }
    if (HTMX_OBJECT_VALUES_RESERVED_INPUT_NAMES.has(key)) {
      throw mappingError(
        `HTMX Action input key "${key}" is incompatible with the HTMX 2.x object-values bridge.`,
      );
    }

    const encoded = encodeActionValue(assertDataProperty(input, key));
    Object.defineProperty(mapped, key, {
      value: encoded,
      enumerable: true,
      writable: true,
      configurable: true,
    });
  }

  for (const required of target.requiredInputNames) {
    if (!hasOwn(input, required)) {
      throw mappingError(`HTMX Action input is missing required key "${required}".`);
    }
  }

  return Object.freeze(mapped);
}
