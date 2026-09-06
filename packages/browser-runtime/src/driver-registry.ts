import type { BindingDriver } from './types.js';

/**
 * Mirrors the frozen runtime-binding driver identifier grammar
 * (spec/0.1 runtime-binding.schema.json). Registration and lookup only
 * accept contract-valid driver names; lookup failures fail closed.
 */
const DRIVER_NAME_PATTERN = /^[a-z][a-z0-9_.:-]{0,79}$/;

function assertValidDriverName(name: string): void {
  if (typeof name !== 'string' || !DRIVER_NAME_PATTERN.test(name)) {
    throw new Error('Invalid binding driver name (must match the frozen driver grammar).');
  }
}

export class DriverRegistry {
  readonly #drivers = new Map<string, BindingDriver>();

  register(name: string, driver: BindingDriver): void {
    assertValidDriverName(name);
    if (this.#drivers.has(name)) throw new Error('Driver already registered.');
    this.#drivers.set(name, driver);
  }

  requireDriver(name: string): BindingDriver {
    assertValidDriverName(name);
    const driver = this.#drivers.get(name);
    if (!driver) throw new Error('Unsupported binding driver.');
    return driver;
  }
}
