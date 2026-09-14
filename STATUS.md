# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/binding-driver-conformance`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Current task:** `T-604 — Shared conformance against Livewire + HTMX`
- **T-604 state:** **IMPLEMENTED / VERIFIED / REVIEWED / READY FOR CLOSURE DECISION**
- **Implementation head:** `9d49c22ac2127c7ade6e235fae488f87490feb43`
- **Implementation validate:** `34793229849` — **7/7 green**
- **Browser at implementation revision:** TypeScript typecheck + **319/319 Vitest across 19 files**
- **Shared matrix:** **22/22** — 11 Livewire + 11 HTMX
- **Fresh T-603 regression:** workflow run `34758253003`, rerun job `103821380728` — **8/8 real Chromium tests**
- **External review PR:** `#13 — test(conformance): add shared Livewire/HTMX binding-driver matrix` — **OPEN**
- **CodeRabbit review:** **LOW risk; 2/2 minor findings confirmed addressed and resolved; 0 unresolved**
- **Review-fix head:** `d8396e36b5e335f89f719f425597cd19ac30a7f3`
- **Review-fix validate:** `34803374097` — **7/7 green**
- **Shared-conformance decision:** `D-058` — **PROPOSED**
- **Portability decision:** `D-020` — **PROPOSED**
- **Next gate:** explicit closure decision; do not promote decisions, close M6, or merge automatically.

## T-604 delivered proof

T-604 proves the genuinely shared browser `BindingDriver` behavior without normalizing away the framework differences:

```text
one shared 11-case behavioral matrix
        ↓
+-------------------------------+
|                               |
Livewire test adapter       HTMX test adapter
|                               |
production                  production
LivewireBrowserDriver       HtmxBrowserDriver
|                               |
controlled fake runtime     controlled fake runtime
```

The matrix is declared exactly once and runs unchanged against both production drivers.

Shared observable semantics:

```text
1. valid exact target dispatches exactly once
2. foreign driver fails binding_target_invalid with zero dispatch
3. malformed driver-owned target fails binding_target_invalid with zero dispatch
4. malformed expiry fails binding_target_invalid with zero dispatch
5. already-expired binding fails binding_expired with zero dispatch
6. expiry equality is expired with zero dispatch
7. unknown Action input fails binding_input_unmappable with zero dispatch
8. missing required Action input fails binding_input_unmappable with zero dispatch
9. missing exact target fails binding_stale with zero dispatch
10. equivalent replacement identity is never silently retargeted
11. already-aborted caller signal surfaces its reason with zero framework dispatch
```

The suite does not assert common target JSON, lifecycle values, framework runtime APIs, framework-local errors, post-dispatch cancellation mechanisms, or successful return values.

## Implementation shape

T-604 implementation is test-only:

```text
packages/browser-runtime/tests/
├── binding-driver-conformance.livewire.test.ts
├── binding-driver-conformance.htmx.test.ts
├── binding-driver-conformance.typecheck.ts
└── support/
    ├── binding-driver-conformance-suite.ts
    ├── livewire-conformance-adapter.ts
    └── htmx-conformance-adapter.ts
```

The `.typecheck.ts` sentinel pulls both adapter/support graphs into the existing package `tsc --noEmit` configuration without changing `tsconfig.json`.

No T-604 implementation change exists under:

```text
packages/browser-runtime/src/**
spec/0.1/**
packages/laravel/src/**
examples/htmx-prep-list/**
.github/workflows/**
```

No package dependency or frozen contract change was introduced. T-701 was not implemented.

## TDD evidence

The implementation retained explicit RED/GREEN evidence for both drivers.

```text
Livewire RED head:          ae82c3d3bec6918a45932c3b11278b64b9ffe9d4
Livewire RED run:           34793105308
Observed boundary:          browser tests failed; typecheck passed

Livewire GREEN head:        bbb594f91ce2983adf0652fdad12d0fc05ce7509
Livewire GREEN run:         34793156495
Observed boundary:          browser job passed

HTMX RED head:              7b04d5e04c6d97e51039c680ee78cba374e78413
HTMX RED run:               34793193056
Observed boundary:          browser tests failed; typecheck passed

Complete GREEN head:        9d49c22ac2127c7ade6e235fae488f87490feb43
Complete GREEN validate:    34793229849 — 7/7 green
```

The RED commits contained only the new entry file before its required adapter existed. The corresponding browser test step failed while package typecheck remained green, providing direct evidence that each new entry was collected and failed before the supporting harness was supplied.

## Verification evidence

Executable evidence is bound to implementation head:

```text
Implementation head:            9d49c22ac2127c7ade6e235fae488f87490feb43
Validate:                       34793229849 — 7/7 jobs green
Contract validation:            PASS
Browser typecheck:              PASS — tsc --noEmit
Shared Livewire matrix:         11/11
Shared HTMX matrix:             11/11
Shared total:                   22/22
Full browser runtime:           19 files / 319/319 tests
```

The complete browser run also kept the original driver-specific proof green, including Livewire driver/cancellation/WebMCP, HTMX driver/input-mapping/cancellation/WebMCP, and shared RuntimeBinding expiry coverage.

### Fresh real-browser regression

The T-603 path-filtered fixture workflow is not triggered by T-604's test-only files, so the already-merged fixture job was explicitly rerun:

```text
Workflow/run:                   34758253003
Fresh rerun job:                103821380728 — SUCCESS
Fixture revision checked out:   e98c919b90f9f19b58ae56b88e391f1abbb179e7
Result:                         8/8 Playwright / real Chromium
```

This is intentionally a regression run of the unchanged T-603 fixture revision. The T-604 diff does not change the fixture, browser-runtime production source, or fixture workflow inputs, so the rerun verifies that the real HTMX portability proof remains intact without pretending that the new test harness is executed inside that fixture.

## External review evidence

PR `#13` reviewed the complete T-604 change through review-prep head `e71248adb7afbce4c286b4be2504344eb3553da2`.

CodeRabbit classified merge risk as **LOW** and raised exactly two actionable findings, both documentation consistency issues:

1. the design document still presented its original `NOT STARTED` implementation state as current;
2. unresolved `D-058` / `D-020` were not duplicated under the repository-required `Needs decision` section.

Fixes:

```text
Design-state fix:        26324a20a640940d138c5954351133276fb4f4fb
Needs-decision fix:      d8396e36b5e335f89f719f425597cd19ac30a7f3
Review-fix validate:     34803374097 — 7/7 green
```

CodeRabbit rechecked each fix in the original inline thread, explicitly confirmed it as addressed, and resolved both threads. Current thread state: **2/2 resolved / 0 unresolved**.

A second full CodeRabbit review was requested for the two docs-only fix commits, but CodeRabbit reported its OSS review-rate limit had been reached. That limitation is recorded explicitly; it is not represented as a completed second full review. The substantive implementation had already received the full review, and each resulting actionable finding was independently rechecked by the reviewer after its fix.

The CodeRabbit docstring-coverage item is a generic pre-merge metric warning, not an inline correctness finding or repository requirement. T-604 introduces test-only harness helpers rather than a new public API; no scope expansion was made solely to satisfy that external metric.

## Diff audit

Compared with `main`, the branch contains:

- the approved T-604 design, implementation plan, decision/tracking documentation;
- exactly six new T-604 browser-runtime test/support files under `packages/browser-runtime/tests/**`;
- documentation-only external-review fixes and review records.

There are no T-604 implementation changes under production browser runtime, Laravel, frozen spec, HTMX fixture, or workflows.

## Decision state

- `D-058` — **PROPOSED**. The shared behavioral conformance implementation is verified and reviewed, but decision promotion waits for explicit closure authorization.
- `D-020` — **PROPOSED**. T-604 supplies the shared-driver evidence, but portability promotion remains a separate explicit closure decision.
- `D-057` and the accepted T-601/T-602 driver decisions remain unchanged.

## Needs decision

The following required architecture decisions remain unresolved and must not be encoded as accepted public contract state before the explicit closure gate:

- `D-058` — decide whether the verified and reviewed shared behavioral binding-driver conformance boundary is accepted.
- `D-020` — separately decide whether the accumulated T-601–T-604 evidence is sufficient to accept HTMX as the materially different second binding used to establish portability.

Until those decisions are explicitly promoted or rejected, M6 remains **IN_PROGRESS**.

## Current boundary

**T-604 is IMPLEMENTED / VERIFIED / REVIEWED / READY FOR CLOSURE DECISION.**

Executable evidence remains bound to `9d49c22ac2127c7ade6e235fae488f87490feb43`. External-review findings were documentation-only and are resolved through `d8396e36b5e335f89f719f425597cd19ac30a7f3`. `D-058` and `D-020` remain **PROPOSED**. Explicit closure authorization is required before decision promotion, M6 closure, or merge.