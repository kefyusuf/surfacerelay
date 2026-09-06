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

Independent deterministic projection of the three supported WebMCP hints. Reviewed checkpoint: `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`.

### T-303 — Async registration lifecycle — DONE / PENDING REVIEW

**Outcome:** current bound-action snapshots are preflighted completely, projected to deterministic versioned WebMCP identities, registered sequentially through a narrow async browser port, and owned by one AbortController-backed disposable registration lease.

**Production additions:**

```text
packages/browser-runtime/src/webmcp-types.ts
packages/browser-runtime/src/webmcp-tool-projection.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
```

**Acceptance:**

- exact ActionDefinition `id + version` must equal the binding action reference;
- canonical WebMCP name is `<action-id>.v<version>`;
- invalid/too-long projected names fail before registration;
- duplicate projected names, including same action/version on multiple bindings, fail before registration with no implicit binding selection;
- supported driver is checked through exact T-301 registry lookup without execution;
- input order is not authority: browser registrations are ASCII-name sorted and sequential;
- one generation shares one registration `AbortSignal`;
- successful registration returns an idempotent disposable lease;
- empty snapshot is valid and makes zero browser calls;
- partial browser registration failure aborts the generation and preserves the original error object;
- registered tool execution preserves the exact captured RuntimeBinding, input and per-execution signal;
- execution cancellation signal is separate from registration lifetime signal;
- T-302 annotations are reused without reinterpretation;
- no `bindingId`/component target data is used to manufacture tool identity;
- no `exposedTo`, automatic reconcile, Livewire browser execution, stale resolution, D-026 finalization, or M4 controls are introduced;
- D-037 and D-038 record the lifecycle and identity boundaries;
- `spec/0.1` remains unchanged.

**TDD / verification:**

```text
Boundary RED:       7fcf21c1c90b701b473b3e569d2430bf00b3b8fe
Boundary RED run:   34063633148
Boundary GREEN:     1f44a7b7d9715727c8b202f9be52a57599345913
Boundary GREEN run: 34063660958

Projection RED:       7c89f5f586ff227def203a57261bf8f8be4febff
Projection RED run:   34063697219
Projection impl:      e604c3f7943497723822903970f8d037ea438390
Projection fixture:   ca8821487656deff188e009b9189e988a8d43ab0
Projection GREEN run: 34063779909

Lifecycle RED:       6e0f2464a1b523b848975673ac8afa78e512686c
Lifecycle RED run:   34063844315
Lifecycle GREEN:     86e91f72ef90c6f9f888ba23e87de2d946923cf5
Lifecycle GREEN run: 34063883296 — all 7 jobs green

Browser:  TypeScript typecheck + 49/49 Vitest tests
PHP:      266 tests / 783 assertions
Contract: 52 fixture manifest entries + 12 conformance scenarios
```

### T-304 — Livewire browser driver — TODO

Execute exact Livewire RuntimeBindings using explicit browser target resolution; stale/unknown bindings must fail with no guessing or retargeting.

**Status:** not started; separate design gate required.

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
