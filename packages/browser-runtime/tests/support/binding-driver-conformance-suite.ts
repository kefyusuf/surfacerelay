import { describe, expect, it } from 'vitest';
import type {
  BindingDriver,
  RuntimeBinding,
} from '../../src/types.js';

export const CONFORMANCE_NOW = new Date('2026-09-14T00:00:00.000Z');

export interface BindingDriverConformanceHarness {
  readonly driver: BindingDriver;
  readonly binding: RuntimeBinding;
  readonly validInput: Record<string, unknown>;
  readonly invalidTargetBinding: RuntimeBinding;
  readonly unknownInput: Record<string, unknown>;
  readonly missingRequiredInput: Record<string, unknown>;

  makeTargetStale(): void;
  replaceTargetWithEquivalentIdentity(): void;
  frameworkDispatchCount(): number;
  replacementDispatchCount(): number;
}

export interface BindingDriverConformanceAdapter {
  readonly name: 'livewire' | 'htmx';
  createHarness(): BindingDriverConformanceHarness;
}

export function defineBindingDriverConformance(
  adapter: BindingDriverConformanceAdapter,
): void {
  describe(`${adapter.name} shared BindingDriver conformance`, () => {
    it('dispatches one valid exact target without normalizing the result', async () => {
      const harness = adapter.createHarness();

      await harness.driver.execute(harness.binding, harness.validInput, {});

      expect(harness.frameworkDispatchCount()).toBe(1);
      expect(harness.replacementDispatchCount()).toBe(0);
    });

    it('rejects a binding owned by another driver before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, driver: 'foreign-driver' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects a malformed driver-owned target before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.invalidTargetBinding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects malformed expiry before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: 'not-a-date' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_target_invalid' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects a binding expired before the conformance clock', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: '2026-09-13T23:59:59Z' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_expired' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('treats expiry equality as expired', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: '2026-09-14T00:00:00Z' },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_expired' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects an unknown Action input before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.binding,
        harness.unknownInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_input_unmappable' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('rejects missing required Action input before framework dispatch', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        harness.binding,
        harness.missingRequiredInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_input_unmappable' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('fails stale when the exact issued target no longer exists', async () => {
      const harness = adapter.createHarness();
      harness.makeTargetStale();

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_stale' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('does not retarget an old binding to an equivalent replacement identity', async () => {
      const harness = adapter.createHarness();
      harness.replaceTargetWithEquivalentIdentity();

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_stale' });

      expect(harness.frameworkDispatchCount()).toBe(0);
      expect(harness.replacementDispatchCount()).toBe(0);
    });

    it('surfaces an already-aborted caller reason with zero framework dispatch', async () => {
      const harness = adapter.createHarness();
      const reason = new Error(`${adapter.name} caller cancelled before dispatch`);
      const controller = new AbortController();
      controller.abort(reason);

      await expect(harness.driver.execute(
        harness.binding,
        harness.validInput,
        { signal: controller.signal },
      )).rejects.toBe(reason);

      expect(harness.frameworkDispatchCount()).toBe(0);
    });
  });
}
