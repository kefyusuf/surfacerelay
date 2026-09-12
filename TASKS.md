# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

> Historical per-task evidence through T-505 is preserved in `docs/archive/TASKS-through-T505-preclosure.md`. Design specs, implementation plans, `STATUS.md`, `REVIEW_REQUEST.md`, PR discussions, and Git history contain the detailed evidence for later tasks.

## M0 — Contract Foundation — DONE

- T-001 through T-005 — DONE.

## M1 — Laravel Kernel — DONE

- T-101 through T-110 — DONE.

## M1.1 — Hardening — DONE / REVIEWED

- Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

- T-201 through T-204 — DONE / REVIEWED.
- Reviewed checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

- T-301 — DriverRegistry — DONE / REVIEWED.
- T-302 — WebMCP semantic projection — DONE / REVIEWED.
- T-303 — Async registration lifecycle — DONE / REVIEWED / MERGED.
- T-304 — Livewire browser driver — DONE / REVIEWED / MERGED.
- T-305 — Cancellation propagation — DONE / REVIEWED / MERGED.

## M4 — Production Trust Controls — DONE / REVIEWED / MERGED

- T-401 — Confirmation challenge/receipt — DONE / REVIEWED.
- T-402 — Idempotency store — DONE / REVIEWED.
- T-403 — Output policy/redaction — DONE / REVIEWED.
- T-404 — Structured audit events — DONE / REVIEWED.

## M5 — Filament Vertical — DONE / REVIEWED / MERGED / MAIN REVALIDATED

- T-501 — Record context binding — DONE / REVIEWED.
- T-502 — Current-selection trusted context — DONE / REVIEWED.
- T-503 — Active-filter context — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-504 — Confirmation bridge — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-505 — Multi-tenant order operations demo — DONE / REVIEWED / MERGED / MAIN REVALIDATED.

T-505 final closure:

```text
Final feature head:       85570928b5e20277d94d2a95ec30028779966112
Merge commit:             7b95a82423012bf2824e55ba052ce78106f52e9a
Post-merge main CI:       34620944364 — 7/7 green
Final closure main:       5b22eef928d2fb1f8fac8ab13507e2f22661d3df
Final closure CI:         34621807162 — 7/7 green
PHP:                      595 tests / 3164 assertions
Browser baseline:         TypeScript typecheck + 103/103 Vitest
```

**M5 is closed.**

## M6 — HTMX Portability Proof — IN_PROGRESS

### T-601 — Explicit HTMX binding descriptor — DONE / REVIEWED / MERGED / MAIN REVALIDATED

**Outcome:** browser-runtime contains a pure driver-owned HTMX RuntimeBinding descriptor proving that the existing generic RuntimeBinding envelope can carry a materially different exact target without introducing HTMX execution, Laravel coupling, or a frozen wire-contract change.

Accepted descriptor boundaries:

- `driver=htmx`, `lifecycle=page`;
- exact target keys: `sourceId`, `method`, `path`, `inputNames`, `requiredInputNames`;
- exact five-method subset and bounded same-origin absolute-path-reference grammar;
- finite named input mapping derived only from a closed top-level ActionDefinition object schema;
- open/reference/composed/conditional mapping forms fail closed;
- `dependentRequired` and legacy `dependencies` are explicitly rejected after external-review hardening;
- required names are a unique non-empty subset of exact property names;
- nested input values are not flattened;
- arbitrary RuntimeBinding JSON is independently consumer-validated;
- output target/list snapshots are defensive and frozen;
- no actor, tenant, role, record, selection, browser-session, confirmation, idempotency, or authorization authority enters the target;
- no DOM/HTMX/network/cancellation behavior exists in T-601;
- no HTMX dependency, Laravel production source, or `spec/0.1/**` change;
- D-053 is **ACCEPTED for descriptor semantics only**;
- D-020 remains **PROPOSED** through T-604.

Design / plan:

```text
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
```

Final closure:

```text
Final feature head:           e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Merge commit:                 96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:           34657967629 — 7/7 green
Browser on main:              TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:      71/71
PHP baseline:                 595 tests / 3164 assertions
Contract / PHP lint:          green
```

**T-601 is closed.**

### T-602 — HTMX browser driver — IN_PROGRESS / DESIGN SPEC UNDER REVIEW

**Implementation state:** **NOT STARTED.**

Design gate approved in chat on 2026-09-12. Written spec:

```text
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
```

Selected boundaries:

- consume the exact T-601 `driver=htmx`, `lifecycle=page` descriptor without changing frozen protocol contracts;
- host-runtime adapter pattern; do not bundle/import a second HTMX runtime;
- support the HTMX 2.x reference runtime only;
- resolve exactly one current `data-surfacerelay-htmx-source` instance; 0 or 2+ matches fail stale;
- require exactly one explicit physical five-verb HTMX request attribute and exact raw method/path equality;
- enforce a same-origin pre-dispatch check without claiming to sandbox later host `htmx:configRequest` hooks;
- map only allowlisted own Action-input names;
- recursively accept deterministic JSON-data values and JSON-string encode structured values under one top-level name;
- reject lossy/non-JSON values and nested invalid values;
- preserve ordinary host form/request state only as untrusted host state;
- fail closed for `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, and active browser validation on the reference source;
- fail `htmx_source_busy` instead of entering HTMX queue/replace/abort behavior when the exact source is already busy;
- strong no-dispatch cancellation applies only before the `htmx.ajax()` invocation frontier;
- after the frontier, do not call `htmx:abort` or replace the natural HTMX success/failure outcome;
- preserve `Promise<void>` semantics and propagate underlying HTMX failures unchanged;
- extract strict RuntimeBinding expiry semantics into a shared browser-runtime helper while preserving Livewire behavior;
- add no HTMX, DOM emulator, browser automation, or other dependency;
- prove WebMCP -> DriverRegistry -> HTMX driver integration without claiming T-604 shared conformance.

Proposed decisions:

- `D-054` — HTMX execution boundary — **PROPOSED**;
- `D-055` — HTMX concurrency/cancellation boundary — **PROPOSED**;
- `D-056` — HTMX Action-input integrity — **PROPOSED**;
- `D-020` remains **PROPOSED** and gated on T-604.

Expected implementation surface after written-spec + implementation-plan approval:

```text
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-browser-driver.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts   # expiry-only refactor
```

Expected focused tests:

```text
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
packages/browser-runtime/tests/runtime-binding-expiry.test.ts
```

Explicitly out of scope during T-602:

```text
packages/laravel/src/**
spec/0.1/**
examples/htmx/**
HTMX 4/beta compatibility
raw fetch / generic HTTP driver
HTMX package dependency
DOM emulator / Playwright / Puppeteer dependency
fixture server/app
response HTML -> ActionResult synthesis
T-604 shared conformance
```

**Current T-602 gate:** written design spec review. Do not write the implementation plan or production code until the user approves the committed written spec.

- T-603 — Non-Laravel HTMX fixture app — TODO.
- T-604 — Shared conformance against Livewire + HTMX — TODO.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-601 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-602 is active only at its **written design-spec review gate**. T-602 implementation and implementation planning are **NOT STARTED**. Do not begin T-603 automatically.