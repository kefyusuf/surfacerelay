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

Independent deterministic projection of the supported WebMCP hints. Reviewed checkpoint: `b8904aaf5d2d8d4f551c213c7ff1103aabb9c8d0`.

### T-303 — Async registration lifecycle — DONE / REVIEWED

Whole-snapshot preflight, deterministic versioned tool identity, sequential async registration, one AbortController-backed lease per generation, partial-failure cleanup and exact driver dispatch. Reviewed/merged checkpoint: `961a1715c889cd52b814537646a50e049f1ef9d7`.

### T-304 — Livewire browser driver — DONE / REVIEWED

**Outcome:** trusted Laravel binding issuance carries a deterministic positional Livewire call plan, and the browser executes only the exact mounted target through documented `Livewire.find()` and `$wire.$call()` APIs.

**Acceptance:**

- trusted producer emits exact `inputOrder` + `requiredCount` from ReflectionMethod order;
- schema property/required sets exactly match caller-visible method parameters;
- unsupported signatures and `$wire`/public-state method collisions fail binding issuance;
- non-null Action output plus explicit `void`/`never` return fails binding issuance;
- browser driver requires exact executable Livewire target shape and component lifecycle;
- explicit expiry is validated/enforced before component lookup;
- input mapping rejects unknown keys, missing required own-properties and positional holes;
- exact `Livewire.find(componentId)` is the only target lookup and `$wire.$id` is rechecked;
- missing/replaced component is stale; no first/name/DOM/class/record/method/replacement fallback exists;
- invocation is exact `$wire.$call(method, ...params)` once and returns the raw result;
- arbitrary Livewire/server failures propagate unchanged;
- T-303 WebMCP integration reaches the exact Livewire driver/binding and never retargets to replacement components;
- cancellation signal is not falsely appended to `$call()`; T-305 owns propagation;
- D-039/D-040/D-041 record exact-resolution, call-plan and documented-API-only boundaries;
- `spec/0.1` remains unchanged.

**Verification:**

```text
Design:               98fda16676f667e63611a4470955195e324cf528
Plan:                 76a641a226168053fa056329023e4bb3e7f00e2a
Server RED:           49ca658250f7e39ab2db4ae524c3e6b51a1ec436 / run 34066761247
Server GREEN:         9a1b1b302c32371429eda409e63f44a295324ea0 / run 34066974783
Producer RED:         2d289b1cd11997fdad7b01dbf720a2ffc00cf63f / run 34067044151
Producer GREEN:       5ce49b140866d584b1c286d543cba53aa6b8db2b / run 34067238627
Browser boundary RED: af39243aabea1bbe66caf2af297d39d0cb53c647 / run 34067293183
Boundary GREEN:       1d01fb402087d28c1fa4e5d201af11e678fe5961 / run 34067343303
Driver RED:           7532c3e018b0751972e7dcc87406023979284f63 / run 34067410382
Driver fixes:         2fd575514ccd7a5f8f3faebbede0359da5128393 → 01c7a19607a54c518641b5886d270780cf3409d4 → 7d2783a3763a086558f19da39c618b001ec2b512
Integration proof:   54f82762d06abb7913eb84e6f66593fd6d346146 / run 34067710238
Review checkpoint:   20ac963871a2ffc7730c0cd42747c8a02de72fab / run 34067907647 — all 7 jobs green
Browser:              TypeScript typecheck + 90/90 Vitest tests
PHP:                  283 tests / 815 assertions
Contract:             52 fixture manifest entries + 12 conformance scenarios
```

### T-305 — Cancellation propagation — TODO

Propagate cancellation as far as documented browser/framework surfaces permit without claiming transactional rollback or reversal of already-started effects.

**Status:** next task; not started. Separate design gate required.

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
