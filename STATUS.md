# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-browser-driver`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-601 — Explicit HTMX binding descriptor`
- **T-601 state:** **DONE / EXTERNALLY REVIEWED / MERGED / MAIN REVALIDATED**
- **Current task:** `T-602 — HTMX browser driver`
- **T-602 state:** **IN_PROGRESS — DESIGN APPROVED / IMPLEMENTATION PLAN WRITTEN**
- **T-602 implementation:** **NOT STARTED**
- **Written spec:** `docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md`
- **Implementation plan:** `docs/superpowers/plans/2026-09-12-htmx-browser-driver.md`
- **Design checkpoint:** `42cca940af35b3dff918a641b619d578f839047d` / CI `34682081839` — **7/7 green**
- **Plan checkpoint:** `b297a54b7abe49390a05df2a26e3e42d5cad8c02`
- **Proposed decisions:** `D-054`, `D-055`, `D-056`
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **Next gate:** choose execution mode for the approved implementation plan; do not begin production changes before explicit execution authorization.

## Baseline entering T-602

T-601 closure on `main`:

```text
T-601 final feature head:       e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
T-601 merge commit:             96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:             34657967629 — 7/7 green
Browser baseline:               TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:        71/71
PHP baseline:                   595 tests / 3164 assertions
Contract / PHP lint:            green
T-602 branch base/current main: 536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4
```

`D-053` is accepted for descriptor semantics only. It does not claim DOM resolution, HTMX dispatch, cancellation, result semantics, or cross-driver portability.

## T-602 approved design

The design gate selected a narrow reference browser driver rather than a generic HTMX/HTTP automation layer.

### Execution boundary

```text
RuntimeBinding(driver=htmx)
        ↓
HtmxBrowserDriver
        ↓
exact source + drift + input + busy + cancellation checks
        ↓
host HTMX 2.x public htmx.ajax()
```

Key boundaries:

- existing `BindingDriver`, `DriverExecutionContext`, `DriverRegistry`, and RuntimeBinding contracts remain unchanged;
- the host page's HTMX runtime is adapted through a narrow runtime port; no second HTMX runtime is bundled;
- reference support is HTMX 2.x only;
- exactly one current SurfaceRelay source identity is required;
- exactly one explicit physical `hx-get|post|put|patch|delete` / `data-hx-*` request attribute is required;
- method and raw path are compared exactly; drift is stale;
- same-origin is checked before SurfaceRelay dispatch, while later host HTMX hooks remain host runtime behavior;
- no raw `fetch()` or response-result synthesis is introduced.

### Input integrity

- only allowlisted own Action-input keys are mapped;
- required input names must be present as own properties;
- scalar JSON values map deterministically to strings;
- arrays/plain objects are recursively validated and JSON-string encoded under one top-level name;
- no nested form flattening;
- non-JSON/lossy/executable/binary/custom values fail closed;
- ordinary form/request state remains untrusted host state;
- reference sources reject `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, and active browser validation because they can ambiguously change Action values or dispatch semantics.

### Concurrency and cancellation

- a source already carrying the active HTMX request class fails `htmx_source_busy`;
- SurfaceRelay does not queue, replace, or broadly abort HTMX work;
- strong no-dispatch cancellation applies only before the `htmx.ajax()` invocation frontier;
- after the frontier, caller abort does not imply network/server cancellation, rollback, reversal, or suppression of the natural HTMX result;
- underlying HTMX rejection identity is preserved;
- successful execution preserves `Promise<void>` semantics.

### Shared expiry

T-602 will extract strict RuntimeBinding expiry parsing/classification from the Livewire driver into a shared browser-runtime helper. This is an internal refactor only; Livewire behavior must remain unchanged and fully regression-tested.

## Implementation plan decomposition

The committed plan uses eight independently reviewable TDD units:

```text
1. Shared RuntimeBinding expiry extraction + Livewire regression
2. HTMX execution error taxonomy + narrow HTMX 2.x runtime adapter
3. Deterministic Action-input mapping
4. Exact-source HTMX browser driver core
5. Unsupported source-modifier + busy-source fail-closed gates
6. HTMX cancellation frontier and natural result semantics
7. DriverRegistry/WebMCP integration proof
8. Full verification, D-054/55/56 promotion, tracking, external-review prep
```

Every implementation task carries its own RED/GREEN/commit cycle. The final implementation task stops at external-review readiness and does not begin T-603.

## Proposed decisions

- `D-054` — exact-source HTMX 2.x host execution boundary — **PROPOSED**.
- `D-055` — busy-source + pre-dispatch-only strong cancellation boundary — **PROPOSED**.
- `D-056` — deterministic Action-input integrity and unsupported HTMX mutation mechanisms — **PROPOSED**.

These decisions must not be promoted merely because the design/plan is written. Promotion is gated on verified T-602 implementation.

`D-020` remains **PROPOSED** until the second binding is exercised through T-603 and shared conformance in T-604.

## Expected implementation surface

Production/refactor:

```text
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts   # expiry-only refactor
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/htmx-browser-driver.ts
```

Focused tests:

```text
packages/browser-runtime/tests/runtime-binding-expiry.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
```

Expected unchanged boundaries:

```text
spec/0.1/**
packages/laravel/src/**
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
examples/htmx/**
```

## Verification expectations for implementation

When implementation begins, each task follows RED -> GREEN -> regression -> commit. Final verification requires at minimum:

```text
cd packages/browser-runtime
npm run typecheck
npm test

# repository root
python scripts/validate.py
```

Full CI must remain green, including PHP compatibility matrix and PHP lint. The browser baseline entering T-602 is 174/174 tests; prior tests must not be deleted/weakened to obtain green.

## Current boundary

**T-602 implementation is not started.** The design and implementation plan are committed. The next allowed action is explicit execution-mode authorization for the plan. No production code, external-review handoff, PR, merge, T-603 fixture, or T-604 shared conformance should begin before the corresponding gate.