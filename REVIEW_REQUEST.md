# Review Request — T-807a live Filament + WebMCP proof

Branch `feat/t-807a-filament-live-webmcp`, top of the open stack (#23 → … → #28).

## What changed

- `packages/laravel/testbench.yaml` + `tests/Browser/*`: fixture-only real Filament 5
  panel under `testbench serve`, reusing `FilamentOrderDemoHarness` (actor/tenant fixed
  to `tenant-a`, no login). A `PAGE_END` render hook emits bindings from the production
  `LivewireBindingProducer` as inert JSON inside the page component.
- Fixture tweaks: `OrderResource` gains columns + `->selectable()`; the edit-page stub
  view is wrapped in `<x-filament-panels::page>`; the harness exposes its registry.
- `examples/filament-orders-live`: browser glue + Playwright on flag-enabled Chromium,
  invoking tools through `document.modelContext.executeTool()`.
- **Finding / D-075 (proposed):** Filament 5 syncs table selection to the server only on
  `mountAction`; an agent call reached the server with an empty `current_selection`
  (`required_context_missing`). The glue now pushes the Alpine selection first, as
  Filament does.
- New `filament-live` CI workflow.

## Review focus

- Is pushing Alpine selection before agent calls the right authority (D-049/D-075)?
  Server-side per-record, all-or-nothing tenant authorization is unchanged.
- Fixture provider routes `/__test/*` and runtime file serving: fixture-only, path-pattern bound.
- T-807b (approved retry) intentionally not implemented; needs a receipt-location decision.

## Verification

- `examples/filament-orders-live`: 5/5 on Filament 5.9 / Livewire 4.4 / Laravel 13.34 /
  PHP 8.4 / Chromium 153. RED first: refund failed with `required_context_missing`,
  edit page had no binding (stub view).
- `packages/laravel`: PHPUnit 595 tests OK (2 skipped, unchanged).
- `scripts/validate.py`, release guardrails, `test_laravel_release_candidate` 20/20.
