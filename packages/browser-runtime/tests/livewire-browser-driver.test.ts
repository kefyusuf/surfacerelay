import { describe, expect, it, vi } from 'vitest';
import { LivewireBrowserDriver } from '../src/livewire-browser-driver.js';
import { LivewireBindingExecutionError } from '../src/livewire-errors.js';
import type { LivewireBrowserRuntime, LivewireWire } from '../src/livewire-browser-runtime.js';
import type { RuntimeBinding } from '../src/types.js';

const now = new Date('2026-09-07T00:00:00.000Z');

function binding(overrides: Partial<RuntimeBinding> = {}): RuntimeBinding {
  return {
    bindingId: 'binding-1',
    action: { id: 'prep_list.add_item', version: 1 },
    driver: 'livewire',
    lifecycle: 'component',
    target: {
      componentId: 'component-1',
      method: 'addItem',
      inputOrder: ['name', 'note'],
      requiredCount: 1,
    },
    expiresAt: null,
    ...overrides,
  };
}

function wire(id = 'component-1', result: unknown = { ok: true }): LivewireWire {
  return {
    $id: id,
    $call: vi.fn(async () => result),
  };
}

function runtime(found: LivewireWire | undefined): LivewireBrowserRuntime & { find: ReturnType<typeof vi.fn> } {
  return {
    find: vi.fn(() => found),
  };
}

function driver(found: LivewireWire | undefined): {
  driver: LivewireBrowserDriver;
  livewire: ReturnType<typeof runtime>;
} {
  const livewire = runtime(found);
  return {
    driver: new LivewireBrowserDriver(livewire, { now: () => now }),
    livewire,
  };
}

async function expectCode(promise: Promise<unknown>, code: string): Promise<void> {
  try {
    await promise;
    throw new Error('expected LivewireBindingExecutionError');
  } catch (error) {
    expect(error).toBeInstanceOf(LivewireBindingExecutionError);
    expect((error as LivewireBindingExecutionError).code).toBe(code);
  }
}

describe('LivewireBrowserDriver', () => {
  it('rejects a binding for another driver before runtime lookup', async () => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({ driver: 'htmx' }), {}, {}), 'binding_target_invalid');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('rejects non-component lifecycle before runtime lookup', async () => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({ lifecycle: 'page' }), {}, {}), 'binding_target_invalid');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it.each([
    {},
    { componentId: '', method: 'addItem', inputOrder: ['name'], requiredCount: 1 },
    { componentId: 'component-1', method: '', inputOrder: ['name'], requiredCount: 1 },
    { componentId: 'component-1', method: 'addItem', requiredCount: 1 },
    { componentId: 'component-1', method: 'addItem', inputOrder: ['name'] },
    { componentId: 'component-1', method: 'addItem', inputOrder: ['name', 'name'], requiredCount: 1 },
    { componentId: 'component-1', method: 'addItem', inputOrder: [''], requiredCount: 0 },
    { componentId: 'component-1', method: 'addItem', inputOrder: ['name'], requiredCount: 2 },
    { componentId: 'component-1', method: 'addItem', inputOrder: ['name'], requiredCount: 1.5 },
    { componentId: 'component-1', method: 'addItem', inputOrder: ['name'], requiredCount: 1, extra: true },
  ])('rejects invalid exact Livewire target shape %#', async (target) => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({ target }), {}, {}), 'binding_target_invalid');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('rejects reserved $wire method names before lookup', async () => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({
      target: { componentId: 'component-1', method: 'set', inputOrder: [], requiredCount: 0 },
    }), {}, {}), 'livewire_method_unsupported');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it.each([
    'not-a-date',
    '0000-01-01T00:00:00Z',
    '2026-09-07T24:00:00Z',
    '2026-09-07T00:60:00Z',
    '2026-02-30T00:00:00Z',
    '2026-09-07T00:00:00+24:00',
    '2026-09-07T00:00:00+03:60',
  ])('rejects malformed/non-RFC3339 expiry %s', async (expiresAt) => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({ expiresAt }), { name: 'passport' }, {}), 'binding_target_invalid');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('treats expiry equal to now as expired before lookup', async () => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding({ expiresAt: '2026-09-07T00:00:00Z' }), { name: 'passport' }, {}), 'binding_expired');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('accepts null/absent and future RFC3339 expiry', async () => {
    for (const expiresAt of [null, undefined, '2026-09-07T01:00:00.123456789+00:30']) {
      const targetWire = wire();
      const fixture = driver(targetWire);
      const value = binding();
      if (expiresAt === undefined) delete value.expiresAt;
      else value.expiresAt = expiresAt;

      await expect(fixture.driver.execute(value, { name: 'passport' }, {})).resolves.toEqual({ ok: true });
    }
  });

  it('maps object input using server-issued order, not caller key order', async () => {
    const targetWire = wire();
    const fixture = driver(targetWire);

    await fixture.driver.execute(binding(), { note: 'carry-on', name: 'passport' }, {});

    expect(targetWire.$call).toHaveBeenCalledExactlyOnceWith('addItem', 'passport', 'carry-on');
  });

  it('allows omitted trailing optional parameters', async () => {
    const targetWire = wire();
    const fixture = driver(targetWire);

    await fixture.driver.execute(binding(), { name: 'passport' }, {});

    expect(targetWire.$call).toHaveBeenCalledExactlyOnceWith('addItem', 'passport');
  });

  it('rejects a missing required own property', async () => {
    const fixture = driver(wire());
    const inherited = Object.create({ name: 'inherited' }) as Record<string, unknown>;

    await expectCode(fixture.driver.execute(binding(), inherited, {}), 'binding_input_unmappable');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('rejects unknown input keys instead of silently dropping them', async () => {
    const fixture = driver(wire());
    await expectCode(fixture.driver.execute(binding(), { name: 'passport', extra: true }, {}), 'binding_input_unmappable');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('rejects an optional positional hole before a later supplied value', async () => {
    const fixture = driver(wire());
    const value = binding({
      target: {
        componentId: 'component-1',
        method: 'update',
        inputOrder: ['name', 'note', 'category'],
        requiredCount: 1,
      },
    });

    await expectCode(fixture.driver.execute(value, { name: 'passport', category: 'travel' }, {}), 'binding_input_unmappable');
    expect(fixture.livewire.find).not.toHaveBeenCalled();
  });

  it('resolves only the exact componentId and treats absence as stale', async () => {
    const fixture = driver(undefined);

    await expectCode(fixture.driver.execute(binding(), { name: 'passport' }, {}), 'binding_stale');
    expect(fixture.livewire.find).toHaveBeenCalledExactlyOnceWith('component-1');
  });

  it('rejects a mismatching $wire identity as stale', async () => {
    const targetWire = wire('replacement-component');
    const fixture = driver(targetWire);

    await expectCode(fixture.driver.execute(binding(), { name: 'passport' }, {}), 'binding_stale');
    expect(targetWire.$call).not.toHaveBeenCalled();
  });

  it('returns the exact $call result and invokes the exact method once', async () => {
    const output = { itemId: 'item-1', name: 'passport' };
    const targetWire = wire('component-1', output);
    const fixture = driver(targetWire);

    await expect(fixture.driver.execute(binding(), { name: 'passport' }, {})).resolves.toBe(output);
    expect(fixture.livewire.find).toHaveBeenCalledExactlyOnceWith('component-1');
    expect(targetWire.$call).toHaveBeenCalledExactlyOnceWith('addItem', 'passport');
  });

  it('preserves the exact original Livewire/server rejection', async () => {
    const original = new Error('authorization denied');
    const targetWire: LivewireWire = {
      $id: 'component-1',
      $call: vi.fn(async () => { throw original; }),
    };
    const fixture = driver(targetWire);

    await expect(fixture.driver.execute(binding(), { name: 'passport' }, {})).rejects.toBe(original);
  });

  it('keeps no-signal invocation on the documented $call argument shape', async () => {
    const targetWire = wire();
    const fixture = driver(targetWire);

    await fixture.driver.execute(binding(), { name: 'passport' }, {});

    expect(targetWire.$call).toHaveBeenCalledExactlyOnceWith('addItem', 'passport');
  });

  it('fails explicitly when the resolved runtime object has no callable $call', async () => {
    const broken = { $id: 'component-1', $call: null } as unknown as LivewireWire;
    const fixture = driver(broken);

    await expectCode(fixture.driver.execute(binding(), { name: 'passport' }, {}), 'livewire_runtime_unavailable');
  });
});
