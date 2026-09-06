import { describe, expect, it } from 'vitest';
import { DriverRegistry } from '../src/driver-registry.js';
import type { BindingDriver } from '../src/types.js';

function driver(onExecute?: () => void): BindingDriver {
  return {
    async execute() {
      onExecute?.();
      return undefined;
    },
  };
}

describe('DriverRegistry', () => {
  it('registers and resolves the exact driver object by exact name', () => {
    const registry = new DriverRegistry();
    const livewire = driver();

    registry.register('livewire', livewire);

    expect(registry.requireDriver('livewire')).toBe(livewire);
  });

  it('keeps distinct explicitly registered drivers independent', () => {
    const registry = new DriverRegistry();
    const livewire = driver();
    const htmx = driver();

    registry.register('livewire', livewire);
    registry.register('htmx', htmx);

    expect(registry.requireDriver('livewire')).toBe(livewire);
    expect(registry.requireDriver('htmx')).toBe(htmx);
  });

  it('rejects duplicate registration without replacing the original driver', () => {
    const registry = new DriverRegistry();
    const original = driver();

    registry.register('livewire', original);

    expect(() => registry.register('livewire', driver())).toThrow('Driver already registered.');
    expect(registry.requireDriver('livewire')).toBe(original);
  });

  it('fails closed for a contract-valid but unsupported driver name', () => {
    const registry = new DriverRegistry();
    registry.register('livewire', driver());

    expect(() => registry.requireDriver('livewire.v2')).toThrow('Unsupported binding driver.');
  });

  it.each([
    '',
    'Livewire',
    ' livewire',
    'livewire ',
    '1livewire',
    'a'.repeat(81),
  ])('rejects contract-invalid driver name %j on registration and lookup', (name) => {
    const registry = new DriverRegistry();

    expect(() => registry.register(name, driver())).toThrow('Invalid binding driver name');
    expect(() => registry.requireDriver(name)).toThrow('Invalid binding driver name');
  });

  it('accepts the 80-character grammar boundary and custom identifiers', () => {
    const registry = new DriverRegistry();
    const maxLength = `a${'b'.repeat(79)}`;
    const namespaced = driver();

    registry.register(maxLength, driver());
    registry.register('acme.livewire:v2-custom', namespaced);

    expect(registry.requireDriver(maxLength)).toBeDefined();
    expect(registry.requireDriver('acme.livewire:v2-custom')).toBe(namespaced);
  });

  it('does not trim, lowercase, alias, or fall back during lookup', () => {
    const registry = new DriverRegistry();
    registry.register('livewire', driver());

    expect(() => registry.requireDriver('Livewire')).toThrow('Invalid binding driver name');
    expect(() => registry.requireDriver(' livewire')).toThrow('Invalid binding driver name');
    expect(() => registry.requireDriver('livewire-alias')).toThrow('Unsupported binding driver.');
  });

  it('never executes a driver during registration or lookup', () => {
    let executions = 0;
    const registry = new DriverRegistry();
    const livewire = driver(() => { executions += 1; });

    registry.register('livewire', livewire);
    registry.requireDriver('livewire');

    expect(executions).toBe(0);
  });

  it('rejects non-string registration names instead of coercing them into contract-valid strings', () => {
    const registry = new DriverRegistry();

    expect(() => registry.register(null as unknown as string, driver())).toThrow('Invalid binding driver name');
  });

  it('rejects non-string lookup names before unsupported-driver resolution', () => {
    const registry = new DriverRegistry();

    expect(() => registry.requireDriver(null as unknown as string)).toThrow('Invalid binding driver name');
  });
});
