import { htmxConformanceAdapter } from './support/htmx-conformance-adapter.js';
import { livewireConformanceAdapter } from './support/livewire-conformance-adapter.js';
import type { BindingDriverConformanceAdapter } from './support/binding-driver-conformance-suite.js';

const adapters: readonly BindingDriverConformanceAdapter[] = [
  livewireConformanceAdapter,
  htmxConformanceAdapter,
];

void adapters;
