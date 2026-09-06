# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

## M0 — Contract Foundation — DONE

| Task | Status | Outcome |
|---|---|---|
| T-001 — Lock v0.1 semantic vocabulary | DONE | Protocol-neutral scope/effect/risk/idempotency/context vocabulary. |
| T-002 — Add schema fixture matrix | DONE | Valid/invalid fixture manifest with intended-failure assertions. |
| T-003 — Lock RuntimeBinding lifecycle semantics | DONE | Extensible drivers, lifecycle/expiry/stale fail-closed semantics. |
| T-004 — Align RuntimeBinding ActionReference identity | DONE | Canonical Action ID grammar + drift guard. |
| T-005 — Align Invocation ActionReference identity | DONE | Canonical `id + version` across definition/binding/invocation. |

`spec/0.1` is provisional and re-frozen after M1.1.

## M1 — Laravel Kernel — DONE

| Task | Status | Outcome |
|---|---|---|
| T-101 — ActionDefinition value object | DONE | Immutable protocol-neutral PHP representation. |
| T-102 — ActionRegistry | DONE | Exact `id + version`, duplicate/missing fail loudly. |
| T-103 — Scalar PHP input schema compiler | DONE | Scalar/nullable reflection → JSON Schema. |
| T-104 — Enum/description schema compilation | DONE | Backed enums + explicit parameter descriptions. |
| T-105 — InvocationContext trust boundary | DONE | Trusted `ContextRequirement` entries separated from caller metadata/input. |
| T-106 — ActionBus pipeline shell | DONE | Canonical fail-closed six-stage orchestration + audit finalizer. |
| T-107 — Laravel validation stage | DONE | Explicit per-action rules; validated dataset only. |
| T-108 — Actor/tenant resolver contracts | DONE | Trusted runtime resolution before dispatch. |
| T-109 — Gate/Policy authorization adapter | DONE | Exact trusted actor via user-scoped Gate. |
| T-110 — Normalized ActionResult/Error model | DONE | Safe public result/status/error normalization. |

## M1.1 — Hardening — DONE / REVIEWED

M1.1 hardening and review fixes remain closed. Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

| Task | Status | Outcome |
|---|---|---|
| T-201 — Livewire RuntimeBinding descriptor | DONE | Exact immutable Livewire target binding. |
| T-202 — Explicit Livewire action exposure API | DONE | Method-level explicit allow-list and exact registry resolution. |
| T-203 — Livewire binding producer | DONE | Trusted mounted identity + fresh opaque binding IDs. |
| T-204 — Shared ActionBus E2E | DONE | Real Livewire/Testbench proof of one shared application mutation path. |

Reviewed M2 implementation checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

---

## M3 — Browser Runtime / WebMCP — IN PROGRESS

### T-301 — DriverRegistry — DONE / REVIEW PENDING

**Outcome:** existing browser DriverRegistry completed with executable fail-closed contract tests and a runtime string-type guard.

**Invariants:**

- explicit `register(name, driver)` only;
- exact `requireDriver(name)` lookup only;
- frozen RuntimeBinding driver grammar;
- duplicate registration fails and preserves the original driver;
- unsupported valid names fail closed;
- invalid and non-string runtime names fail before lookup;
- no trim/lowercase/alias/default/fallback;
- registration and lookup never execute drivers;
- registry owns no discovery, lifecycle, stale resolution, authorization, target lookup or WebMCP registration;
- D-035 records the browser registry boundary.

**Verification:**

```text
RED:          5e442a7ae70a59ef2d8b7f5c9bdd3dcc4134d91b
GREEN:        1c1ff62ac70f779e90866bd169abc7599b7632bf
RED run:      34050492466 — browser 16 passed / 2 deliberate failures
GREEN run:    34050557047 — all jobs green
Browser:      TypeScript typecheck + 18/18 Vitest tests
PHP:          266 tests / 783 assertions
Contract:     52 fixture manifest entries + 12 conformance scenarios
```

### T-302 — WebMCP semantic projection — TODO

- `effect == read` → read-only hint;
- `outputContentTrust == contains_untrusted_content` → untrusted-content hint;
- `risk == consequential` → consequential hint.

`outputSensitivity` remains independent and belongs to server-side output policy/redaction.

**Status:** not started; do not begin until T-301 review closes.

### T-303 — Async registration lifecycle — TODO
Register current tools through the browser API, handle errors, and clean up with lifecycle/AbortController semantics.

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
