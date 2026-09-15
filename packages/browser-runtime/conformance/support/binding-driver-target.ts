import type { BindingDriver, RuntimeBinding } from '../../src/types.js';

export const CONFORMANCE_NOW = new Date('2026-09-14T00:00:00.000Z');

export interface BindingDriverConformanceTarget {
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
