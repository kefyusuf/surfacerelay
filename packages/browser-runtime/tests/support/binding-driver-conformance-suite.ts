import { describe, expect, it } from 'vitest';
import {
  CONFORMANCE_NOW,
  type BindingDriverConformanceTarget,
} from '../../conformance/support/binding-driver-target.js';

export interface BindingDriverConformanceAdapter {
  readonly name: 'livewire' | 'htmx';
  createHarness(): BindingDriverConformanceTarget;
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
      const expiredAt = new Date(CONFORMANCE_NOW.getTime() - 1_000).toISOString();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: expiredAt },
        harness.validInput,
        {},
      )).rejects.toMatchObject({ code: 'binding_expired' });

      expect(harness.frameworkDispatchCount()).toBe(0);
    });

    it('treats expiry equality as expired', async () => {
      const harness = adapter.createHarness();

      await expect(harness.driver.execute(
        { ...harness.binding, expiresAt: CONFORMANCE_NOW.toISOString() },
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
