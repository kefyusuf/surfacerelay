import { LivewireBindingExecutionError } from './livewire-errors.js';
import type { LivewireBrowserRuntime, LivewireWire } from './livewire-browser-runtime.js';
import { isLivewireReservedMethodName } from './livewire-reserved-names.js';
import type {
  BindingDriver,
  DriverExecutionContext,
  RuntimeBinding,
} from './types.js';

export interface BrowserClock {
  now(): Date;
}

const systemBrowserClock: BrowserClock = {
  now: () => new Date(),
};

interface LivewireTarget {
  componentId: string;
  method: string;
  inputOrder: string[];
  requiredCount: number;
}

const TARGET_KEYS = ['componentId', 'inputOrder', 'method', 'requiredCount'] as const;
const RFC3339_PATTERN = /^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})(?:\.(\d+))?(Z|([+-])(\d{2}):(\d{2}))$/;

function executionError(
  code: LivewireBindingExecutionError['code'],
  message: string,
): LivewireBindingExecutionError {
  return new LivewireBindingExecutionError(code, message);
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
}

function parseTarget(binding: RuntimeBinding): LivewireTarget {
  if (binding.driver !== 'livewire') {
    throw executionError('binding_target_invalid', 'Livewire driver received a binding for another driver.');
  }
  if (binding.lifecycle !== 'component') {
    throw executionError('binding_target_invalid', 'Livewire browser bindings must use component lifecycle.');
  }
  if (!isRecord(binding.target)) {
    throw executionError('binding_target_invalid', 'Livewire binding target must be an object.');
  }

  const keys = Object.keys(binding.target).sort();
  const expectedKeys = [...TARGET_KEYS].sort();
  if (keys.length !== expectedKeys.length || keys.some((key, index) => key !== expectedKeys[index])) {
    throw executionError('binding_target_invalid', 'Livewire binding target must contain exactly componentId, method, inputOrder, and requiredCount.');
  }

  const componentId = binding.target.componentId;
  const method = binding.target.method;
  const inputOrder = binding.target.inputOrder;
  const requiredCount = binding.target.requiredCount;

  if (typeof componentId !== 'string' || componentId.length === 0) {
    throw executionError('binding_target_invalid', 'Livewire target componentId must be a non-empty string.');
  }
  if (typeof method !== 'string' || method.length === 0) {
    throw executionError('binding_target_invalid', 'Livewire target method must be a non-empty string.');
  }
  if (!Array.isArray(inputOrder)) {
    throw executionError('binding_target_invalid', 'Livewire target inputOrder must be an array.');
  }

  const seen = new Set<string>();
  for (const name of inputOrder) {
    if (typeof name !== 'string' || name.length === 0 || seen.has(name)) {
      throw executionError('binding_target_invalid', 'Livewire target inputOrder must contain unique non-empty strings.');
    }
    seen.add(name);
  }

  if (!Number.isInteger(requiredCount) || requiredCount < 0 || requiredCount > inputOrder.length) {
    throw executionError('binding_target_invalid', 'Livewire target requiredCount is outside inputOrder bounds.');
  }

  return {
    componentId,
    method,
    inputOrder: [...inputOrder],
    requiredCount,
  };
}

function daysInMonth(year: number, month: number): number {
  if (month === 2) {
    const leap = year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0);
    return leap ? 29 : 28;
  }
  return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

/** Strict parser for the frozen RuntimeBinding RFC3339-oriented date-time contract. */
function parseRfc3339Millis(value: string): number | null {
  const match = RFC3339_PATTERN.exec(value);
  if (!match) return null;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const hour = Number(match[4]);
  const minute = Number(match[5]);
  const second = Number(match[6]);
  const fraction = match[7] ?? '';
  const zone = match[8];
  const offsetSign = match[9];
  const offsetHour = match[10] === undefined ? 0 : Number(match[10]);
  const offsetMinute = match[11] === undefined ? 0 : Number(match[11]);

  if (year === 0 || month < 1 || month > 12) return null;
  if (day < 1 || day > daysInMonth(year, month)) return null;
  if (hour < 0 || hour > 23 || minute < 0 || minute > 59 || second < 0 || second > 59) return null;
  if (zone !== 'Z' && (offsetHour < 0 || offsetHour > 23 || offsetMinute < 0 || offsetMinute > 59)) return null;

  const milliseconds = Number((fraction.slice(0, 3) + '000').slice(0, 3));
  const local = new Date(0);
  local.setUTCFullYear(year, month - 1, day);
  local.setUTCHours(hour, minute, second, milliseconds);

  if (
    local.getUTCFullYear() !== year
    || local.getUTCMonth() !== month - 1
    || local.getUTCDate() !== day
    || local.getUTCHours() !== hour
    || local.getUTCMinutes() !== minute
    || local.getUTCSeconds() !== second
  ) {
    return null;
  }

  let offsetMinutes = 0;
  if (zone !== 'Z') {
    offsetMinutes = offsetHour * 60 + offsetMinute;
    if (offsetSign === '-') offsetMinutes *= -1;
  }

  return local.getTime() - offsetMinutes * 60_000;
}

function assertNotExpired(binding: RuntimeBinding, now: Date): void {
  if (binding.expiresAt === undefined || binding.expiresAt === null) return;
  if (typeof binding.expiresAt !== 'string') {
    throw executionError('binding_target_invalid', 'Livewire binding expiresAt must be an RFC3339 string or null.');
  }

  const expiresAt = parseRfc3339Millis(binding.expiresAt);
  if (expiresAt === null) {
    throw executionError('binding_target_invalid', 'Livewire binding expiresAt is not a valid RFC3339 date-time.');
  }
  if (expiresAt <= now.getTime()) {
    throw executionError('binding_expired', 'Livewire binding has expired.');
  }
}

function mapInput(target: LivewireTarget, input: Record<string, unknown>): unknown[] {
  const allowed = new Set(target.inputOrder);
  for (const key of Object.keys(input)) {
    if (!allowed.has(key)) {
      throw executionError('binding_input_unmappable', `Livewire binding input contains unknown key "${key}".`);
    }
  }

  const hasOwn = (key: string): boolean => Object.prototype.hasOwnProperty.call(input, key);
  for (let index = 0; index < target.requiredCount; index += 1) {
    if (!hasOwn(target.inputOrder[index])) {
      throw executionError('binding_input_unmappable', `Livewire binding input is missing required key "${target.inputOrder[index]}".`);
    }
  }

  let lastPresent = -1;
  for (let index = target.inputOrder.length - 1; index >= 0; index -= 1) {
    if (hasOwn(target.inputOrder[index])) {
      lastPresent = index;
      break;
    }
  }

  for (let index = 0; index <= lastPresent; index += 1) {
    const name = target.inputOrder[index];
    if (!hasOwn(name)) {
      throw executionError('binding_input_unmappable', `Livewire binding input cannot omit positional key "${name}" before a later supplied value.`);
    }
  }

  return target.inputOrder.slice(0, lastPresent + 1).map((name) => input[name]);
}

function assertCallableWire(wire: LivewireWire): void {
  if (typeof wire.$call !== 'function') {
    throw executionError('livewire_runtime_unavailable', 'Resolved Livewire component does not expose callable $call().');
  }
}

export class LivewireBrowserDriver implements BindingDriver {
  constructor(
    private readonly livewire: LivewireBrowserRuntime,
    private readonly clock: BrowserClock = systemBrowserClock,
  ) {}

  async execute(
    binding: RuntimeBinding,
    input: Record<string, unknown>,
    _context: DriverExecutionContext,
  ): Promise<unknown> {
    const target = parseTarget(binding);
    assertNotExpired(binding, this.clock.now());

    if (isLivewireReservedMethodName(target.method)) {
      throw executionError('livewire_method_unsupported', `Livewire method "${target.method}" collides with the documented $wire surface.`);
    }

    const params = mapInput(target, input);
    const resolved = this.livewire.find(target.componentId);
    if (resolved === undefined) {
      throw executionError('binding_stale', 'Exact Livewire component is no longer mounted.');
    }
    if (resolved.$id !== target.componentId) {
      throw executionError('binding_stale', 'Livewire.find() resolved a component with a different identity.');
    }

    assertCallableWire(resolved);
    return resolved.$call(target.method, ...params);
  }
}
