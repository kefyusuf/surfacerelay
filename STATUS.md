# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Milestone:** `M6 — HTMX Portability Proof` — **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **Last completed task:** `T-604 — Shared conformance against Livewire + HTMX`
- **T-604 state:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **Pull request:** `#13 — test(conformance): add shared Livewire/HTMX binding-driver matrix` — **MERGED**
- **Implementation head:** `9d49c22ac2127c7ade6e235fae488f87490feb43`
- **Implementation validate:** `34793229849` — **7/7 green**
- **Shared matrix:** **22/22** — 11 Livewire + 11 HTMX
- **Browser at implementation revision:** TypeScript typecheck + **319/319 Vitest across 19 files**
- **Fresh T-603 real-browser regression:** job `103821380728` — **8/8 Playwright / real Chromium**
- **External review:** CodeRabbit **LOW risk; 2/2 actionable findings resolved; 0 unresolved**
- **Decision-promotion head:** `bfcbde90ba2892f2e192a1df8d5c7c84310b23c0`
- **Decision-promotion PR validate:** `34806672450` — **7/7 green**
- **Shared-conformance decision:** `D-058` — **ACCEPTED**
- **Portability decision:** `D-020` — **ACCEPTED**
- **Merge commit:** `2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080`
- **Post-merge main validate:** `34806790431` — **7/7 green**
- **Post-merge browser:** TypeScript typecheck + **319/319 Vitest across 19 files**
- **Next task:** `T-701 — Executable conformance runner` — **TODO / NOT STARTED**
- **Next gate:** explicit authorization to begin T-701; do not start it automatically.

## M6 delivered proof

M6 now contains four cumulative HTMX portability proofs:

```text
T-601: generic RuntimeBinding carries an explicit HTMX page/source target
        ↓
T-602: production HtmxBrowserDriver validates and executes that target fail-closed
        ↓
T-603: real non-Laravel Node + HTMX 2.x + Chromium fixture proves the path end-to-end
        ↓
T-604: one shared executable matrix proves the common BindingDriver invariants
        against materially different Livewire and HTMX implementations
```

The resulting portability claim is deliberately bounded. HTMX does **not** look like Livewire internally. The evidence shows that the generic SurfaceRelay binding envelope and common fail-closed invariants survive materially different framework-owned target/runtime semantics.

## T-604 shared conformance

T-604 adds one test-only matrix declared exactly once and executed unchanged against both production drivers:

```text
one shared 11-case suite
        ↓
+-------------------------------+
|                               |
Livewire test adapter       HTMX test adapter
|                               |
production                  production
LivewireBrowserDriver       HtmxBrowserDriver
```

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

The suite does not assert common target JSON, common lifecycle values, common runtime APIs, framework-local errors, post-dispatch cancellation mechanisms, or common successful return values.

## Implementation boundary

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

Explicitly unchanged by the T-604 implementation:

```text
packages/browser-runtime/src/**
spec/0.1/**
packages/laravel/src/**
examples/htmx-prep-list/**
.github/workflows/**
packages/browser-runtime/package*.json
packages/browser-runtime/tsconfig.json
```

No production driver abstraction, frozen contract change, package dependency expansion or early T-701 runner was introduced.

## Verification evidence

```text
Implementation head:            9d49c22ac2127c7ade6e235fae488f87490feb43
Implementation validate:        34793229849 — 7/7 green
Livewire shared matrix:         11/11
HTMX shared matrix:             11/11
Shared total:                   22/22
Full browser runtime:           19 files / 319/319 + typecheck
Fresh T-603 regression job:     103821380728 — 8/8 real Chromium

External-review fix head:       d8396e36b5e335f89f719f425597cd19ac30a7f3
External-review fix validate:   34803374097 — 7/7 green
Review threads:                 2/2 resolved / 0 unresolved

Decision-promotion head:        bfcbde90ba2892f2e192a1df8d5c7c84310b23c0
Decision-promotion PR validate: 34806672450 — 7/7 green
Merge commit:                   2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
Post-merge main validate:       34806790431 — 7/7 green
Post-merge browser:             19 files / 319/319 + typecheck
```

## External review closure

CodeRabbit reviewed the substantive T-604 PR and classified merge risk as **LOW**. It raised two actionable findings, both documentation consistency issues:

1. the design document was presenting its original design-gate `NOT STARTED` state as current;
2. unresolved `D-058` / `D-020` were not listed under `STATUS.md > Needs decision` as required by `AGENTS.md`.

Both were fixed, directly rechecked by CodeRabbit in their original threads, and resolved. No implementation correctness finding remained unresolved. A requested second full sweep of only the docs-only fixes was rate-limited by CodeRabbit; this is recorded as a limitation rather than represented as a completed second review.

## Decision closure

### D-058 — ACCEPTED

The tested shared browser binding-driver boundary is behavioral rather than structural. Livewire and HTMX pass the same executable matrix for target validation, expiry, Action-input mappability, exact-target stale/no-retarget semantics, dispatch counting, and pre-dispatch cancellation. Framework-specific target structures, lifecycles, runtime APIs, errors, cancellation mechanisms and successful results stay framework-owned.

### D-020 — ACCEPTED

HTMX is accepted as the materially different second binding used to establish portability. Evidence spans:

- T-601: page-scoped `sourceId + method + path + named inputs` descriptor semantics;
- T-602: HTMX 2.x public runtime execution and HTMX-specific validation/dispatch behavior;
- T-603: real non-Laravel HTMX application and Chromium execution;
- T-604: the same shared conformance matrix passing against Livewire and HTMX.

This meets the two materially different bindings + shared executable scenarios threshold established by D-009.

**Important boundary:** meeting that threshold does not itself extract or publish a standalone cross-framework specification. Any such promotion remains a separate future architecture/product decision.

## Current boundary

**M6 is closed. T-604 is closed.**

`D-058` and `D-020` are accepted. `main` has been revalidated after PR #13. M7 remains future work; T-701 has **not started** and requires a new explicit user gate.