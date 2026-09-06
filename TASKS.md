# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

## M0 — Contract Foundation — DONE

All T-001..T-005 DONE.

## M1 — Laravel Kernel — DONE

All T-101..T-110 DONE.

## M1.1 — Hardening — DONE / REVIEWED

Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

T-201 through T-204 DONE / REVIEWED. Reviewed M2 checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

---

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEWED

Exact explicit driver registration/lookup with fail-closed invalid/unknown handling. Reviewed checkpoint: `df55267a72a93ed7a3017c810469fd5c0ff1b6f4`.

### T-302 — WebMCP semantic projection — DONE / REVIEWED

**Outcome:** deterministic orthogonal projection of three core semantics:

- `effect == read` → `readOnlyHint`;
- `outputContentTrust == contains_untrusted_content` → `untrustedContentHint`;
- `risk == consequential` → `consequentialHint`.

**Acceptance:**

- no risk inference from destructive/external effects;
- no content-trust inference from output sensitivity;
- `read + consequential` preserves both true hints;
- no unrelated/unsupported hint synthesis from sensitivity, effect or idempotency;
- ActionDefinition remains unchanged;
- projection owns no registration/execution/binding/authorization behavior;
- returned projection shape is deterministic: all three booleans are present and required in the TypeScript type;
- D-036 records the independent mapping boundary.

**Verification:**

```text
RED:               8ef99e86331b1a6d81f4755ab7cd65294b639075
GREEN:             13253898b568bd0a52b0a2dc8d3d9f0be113483f
Reviewed/merged:   b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0
RED run:           34052956365
GREEN run:         34053052459
Review run:        34053277732
Merged main run:   34053337527
Browser:           TypeScript typecheck + 30/30 Vitest tests
PHP:               266 tests / 783 assertions
Contract:          52 fixture manifest entries + 12 conformance scenarios
CI:                all 7 jobs green on feature review and merged main
```

### T-303 — Async registration lifecycle — TODO
Register current tools through the browser API, handle errors, and clean up with lifecycle/AbortController semantics.

**Status:** next task, not started.

### T-304 — Livewire browser driver — TODO
Execute Livewire RuntimeBindings; stale/unknown bindings return explicit failures with no guessing.

### T-305 — Cancellation propagation — TODO
Propagate cancellation as far as supported without claiming transactional rollback.

---

## M4 — Production Trust Controls — TODO

- T-401 — Confirmation challenge/receipt.
- T-402 — Idempotency store.
- T-403 — Output policy/redaction.
- T-404 — Structured audit events.

## M5 — Filament Vertical — TODO

- T-501 — Record context binding.
- T-502 — Current-selection trusted context.
- T-503 — Active-filter context.
- T-504 — Confirmation bridge.
- T-505 — Multi-tenant order operations demo.

## M6 — HTMX Portability Proof — TODO

- T-601 — Explicit HTMX binding descriptor.
- T-602 — HTMX browser driver.
- T-603 — Non-Laravel HTMX fixture app.
- T-604 — Shared conformance against Livewire + HTMX.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner.
- T-702 — Adapter author guide.
- T-703 — Laravel MCP projection using a maintained MCP implementation.
- T-704 — Optional OpenAPI importer as a secondary adapter.
