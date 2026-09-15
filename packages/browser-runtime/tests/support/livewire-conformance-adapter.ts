import { createLivewireConformanceTarget } from '../../conformance/support/livewire-target.js';
import type { BindingDriverConformanceAdapter } from './binding-driver-conformance-suite.js';

export const livewireConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'livewire',
  createHarness: createLivewireConformanceTarget,
};
