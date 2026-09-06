# Prep List Example

This directory contains static contract examples for `prep_list.add_item@1`.

The `binding.livewire.json` values are illustrative snapshots, not reusable runtime authority. In the implemented Livewire reference path:

- `bindingId` is freshly issued by the trusted runtime;
- `target.componentId` comes from the exact mounted Livewire component instance;
- replacement components receive different exact targets;
- old bindings are never silently retargeted.

T-204 proves the shared server-side application path with the real Livewire test harness. The browser-side RuntimeBinding resolver/driver is intentionally deferred to M3/T-304.
