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

Independent deterministic projection of supported WebMCP hints. Reviewed checkpoint: `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`.

### T-303 — Async registration lifecycle — DONE / REVIEWED

Whole-snapshot preflight, deterministic tool identity, sequential registration, registration leases and partial-failure cleanup. Reviewed/merged checkpoint: `961a1715c889cd52b814537646a50e049f1ef9d7`.

### T-304 — Livewire browser driver — DONE / REVIEWED

Exact Livewire binding execution through documented `Livewire.find()` + `$wire.$call()`, server-issued positional call plan, strict expiry/input checks and no stale-target retargeting. Reviewed/merged checkpoint: `bd1f20397a6a3f24000abb73ac7eedb6ed48fdb9`.

### T-305 — Cancellation propagation — DONE / READY FOR EXTERNAL-STYLE REVIEW

**Outcome:** SurfaceRelay propagates cancellation only as far as it can make a truthful guarantee: exact action-level cancellation before Livewire dispatch, with a hard `onSend` frontier and no rollback/reversal claim after dispatch.

**Acceptance:**

- WebMCP `ToolExecuteCallbackOptions.signal` is required by TypeScript;
- already-aborted WebMCP execution preserves exact abort reason and does not perform invocation-time driver lookup/execution;
- registration-time driver preflight remains separate and unchanged;
- direct Livewire driver execution checks already-aborted signals before runtime lookup;
- cancellation-aware Livewire execution requires documented component-scoped `intercept(method, callback)` support before `$call()`;
- only the exact action handle created by the immediate exact `$call()` is captured;
- pre-`onSend` abort invokes exact `action.cancel()` once and surfaces exact caller abort reason;
- synchronous prior-interceptor abort race is handled after exact action capture;
- `onSend` is the dispatch frontier;
- post-`onSend` abort never calls broad action/message/request cancellation and does not replace natural success/failure;
- no `#[Async]`, `#[Isolate]`, private request API or synthetic rollback result is introduced;
- interceptor cleanup avoids mutating Livewire's interceptor array during callback iteration;
- unrelated trailing interceptors run and later same-method invocations are not captured;
- execution-local AbortSignal listeners are cleaned on success, failure and pre-dispatch cancellation;
- D-042/D-043 record the cancellation frontier and granular Livewire cancellation boundary;
- `spec/0.1` and Laravel production remain unchanged.

**Verification:**

```text
Design:                     2d044bdde2fdf8f5b084ce5cebf888aa2c293319
Design hardening:           961262931676d1102555cd31c6e5dafa3ad19b30
Plan:                       e33b74439055dfabab40ceb99850404d420b314b
WebMCP RED:                 b270a2b26d45ba8826128c616f97d3897e857ac9 / 34098620894
Task-1 GREEN:               cf884ed85f57f8dfeb21cf68411fd820e9979ceb / 34099618870 — 7/7 green
Interceptor type RED:       f8811fd3ef46a0a2616524d7499b23de926e43f6 / 34102239036
Interceptor port:           fb00a2123b449ba25e240cf6253856fcbcdc72e5
Cancellation RED:           ba7240eee58caee2b1132802d01c4a6ef13d245d / 34102948252
Cancellation implementation: fffff7b15a31d61fc1cb212598505eba044b86b2
Implementation GREEN:       35430366e8d01d17a5d936ac55050848face2bbc / 34103990010 — 7/7 green
Coverage hardening:         6cfd7d11862d51dcd6c1e2c18254b290c661e2ec / 34104686684 — 7/7 green
Browser:                    TypeScript typecheck + 103/103 Vitest tests
PHP:                        283 tests / 815 assertions
Contract:                   52 fixture entries + 12 conformance scenarios
```

**Review status:** implementation complete; exact branch diff and fresh review-checkpoint CI still required before merge.

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
