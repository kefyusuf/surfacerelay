import { createHtmxConformanceTarget } from '../../conformance/support/htmx-target.js';
import type { BindingDriverConformanceAdapter } from './binding-driver-conformance-suite.js';

export const htmxConformanceAdapter: BindingDriverConformanceAdapter = {
  name: 'htmx',
  createHarness: createHtmxConformanceTarget,
};
