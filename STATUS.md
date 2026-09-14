# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/executable-conformance-runner`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Last completed task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Current task:** `T-701 — Executable conformance runner`
- **T-701 state:** **IN_PROGRESS / DESIGN APPROVED / PLAN WRITTEN / IMPLEMENTATION NOT STARTED**
- **Design:** `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- **Plan:** `docs/superpowers/plans/2026-09-15-executable-conformance-runner.md`
- **Design branch base:** `main@67474d98152c7883af6106c92ca327bcd2e87307`
- **Plan self-review head:** `5eafdeb4e6c462c6e5a0c422ea79eec368d76c65`
- **Proposed decisions:** `D-059`, `D-060`, `D-061` — **PROPOSED**
- **Existing provisional decision:** `D-026` — remains **PROPOSED**
- **Next gate:** explicit user authorization to execute the written T-701 implementation plan; implementation must not start automatically.

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

## T-701 approved design boundary

T-701 turns a bounded subset of canonical runtime conformance semantics into a repo-local executable runner without turning the runner into a public standard or runtime framework.

### Claimed profile and targets

```text
profile: runtime-binding/driver

targets:
  browser/livewire
  browser/htmx
```

`browser/driver-registry` was considered during design and explicitly removed from v1 because the accepted raw observation vocabulary does not soundly prove exact registered-driver identity without adding interpretive/self-reported fields.

### Canonical executable matrix

```text
BIND-EXACT-TARGET-EXECUTES       Livewire PASS expected / HTMX PASS expected
BIND-EXPIRED-NOT-EXECUTABLE     Livewire PASS expected / HTMX PASS expected
BIND-COMPONENT-STALE             Livewire PASS expected / HTMX NOT_APPLICABLE
BIND-NO-SILENT-RETARGET          Livewire PASS expected / HTMX PASS expected
```

Expected full canonical v1 result after implementation:

```text
7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE
```

`BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` remain documented rather than executable in T-701 v1.

### Core runner boundary

- profiles define atomic conformance claim boundaries;
- capabilities define conditional applicability inside a profile;
- adapters declare profile/capability only; they cannot choose the scenario set;
- mandatory scenarios cannot be adapter-skipped;
- `NOT_APPLICABLE` is runner-owned and determined before process execution;
- harnesses emit raw observations only;
- the runner alone compares canonical expectations and decides `PASS` / `FAIL`;
- infrastructure/protocol failures are `ERROR`, not conformance `FAIL`;
- every claimed profile must have at least one mandatory positive executable scenario;
- `recommendedCode` remains advisory so T-701 does not silently promote D-026.

### Process protocol

```text
canonical scenario registry
          ↓
profile/capability selection
          ↓
Python stdlib runner
       /          \
      /            \
fresh Livewire    fresh HTMX
subprocess         subprocess
      \            /
       \          /
       raw observations
          ↓
canonical evaluator
          ↓
PASS / FAIL / ERROR / NOT_APPLICABLE
```

One target/scenario pair uses one fresh subprocess. Request is one JSON document on stdin; stdout is exactly one protocol JSON containing raw observation; stderr is diagnostics. Expected results are not sent to harnesses. Fixed v1 timeout is 10 seconds.

Runner exit semantics:

```text
0 → all applicable scenarios PASS
1 → at least one FAIL and no ERROR
2 → at least one ERROR
```

### Semantic vs execution ownership

Canonical runtime scenario semantics remain in:

```text
spec/0.1/fixtures/conformance-scenarios.json
```

Repo-local target command/profile/capability wiring remains outside the spec.

`scripts/validate.py` remains responsible for structural integrity; `scripts/run_conformance.py` will be responsible for runtime execution and verdicts. The runner will not import Livewire, HTMX, or Laravel runtime implementations and will not act as a package/build manager.

### Test/harness reuse boundary

T-701 should extract reusable Vitest-free controlled target setup so T-604 tests and T-701 harnesses do not drift into two independent Livewire/HTMX behavioral models.

Production browser runtime files under `packages/browser-runtime/src/**` are not expected to require semantic changes for T-701 v1.

## T-701 implementation plan gate

The committed implementation plan is:

```text
docs/superpowers/plans/2026-09-15-executable-conformance-runner.md
```

Plan commits:

```text
Initial plan:          0fed4f3015a7e39a97906646f82a6a9c7dacc527
Self-review refinement: 5eafdeb4e6c462c6e5a0c422ea79eec368d76c65
```

The plan is split into eight reviewable TDD units:

```text
1. Python selection/evaluation model
2. strict subprocess runner + fake-harness protocol tests
3. shared Vitest-free browser target support preserving T-604
4. raw observation executor + Livewire harness
5. HTMX harness on the same executor/protocol
6. canonical registry promotion + target manifests + structural validation
7. seven-job CI integration + implemented conformance docs
8. review/tracking handoff, then STOP before merge/decision promotion
```

Plan self-review findings were resolved before this status update:

- no placeholder/TBD items remain;
- an undefined `ConformanceConfigError` interface reference was removed;
- Task 4 now uses one explicit permanent raw-observation Vitest file rather than an ambiguous temporary-test choice;
- Node process typing is dependency-free through a local structural `globalThis.process` adapter;
- production-source diff checks and decision-status checks are explicit.

## Needs decision

The following T-701 decisions remain intentionally unresolved until implementation evidence and external review exist:

- `D-059` — **PROPOSED** — conformance authority uses profile + capability; runner owns canonical scenario selection and PASS/FAIL, harnesses emit raw observations only.
- `D-060` — **PROPOSED** — language-neutral one-scenario/one-subprocess JSON protocol with Python stdlib orchestrator, stderr diagnostics and fixed timeout.
- `D-061` — **PROPOSED** — canonical semantics stay in the scenario registry, execution wiring stays outside the spec, and every executable claimed profile requires a mandatory positive scenario; v1 claims only browser `runtime-binding/driver`.
- `D-026` — remains **PROPOSED** independently; recommended binding failure codes are not made globally normative by T-701.

No implementation should promote D-059/D-060/D-061 automatically merely because tests pass. Promotion requires a later explicit decision gate after executable verification and external review.

## Required later implementation evidence

```text
Python runner unit tests          PASS
python scripts/validate.py        PASS
browser TypeScript typecheck      PASS
full browser Vitest suite         PASS
conformance harness compilation   PASS
T-701 runtime matrix              7 PASS / 1 NOT_APPLICABLE
full validate workflow            7/7 green
full diff review                  PASS
external review                   completed
```

Runtime-binding identity/lifecycle remains security-sensitive, so negative evidence must include expiry no-dispatch, stale exact-target no-dispatch, and equivalent-replacement zero-dispatch proof.

## Explicit non-goals

T-701 v1 does not create or claim:

- a public conformance SDK/package;
- standalone SurfaceRelay specification extraction;
- Laravel/PHP conformance target coverage;
- binding lookup, driver-registry, or action-availability conformance;
- Trust/confirmation/idempotency conformance;
- Output/Projection conformance;
- real-browser orchestration inside the runner;
- remote targets, parallelism, retries, watch mode, plugin discovery, or generic assertion DSL;
- automatic work on T-702, T-703, or T-704.

## Current boundary

**M6 is closed. T-604 is closed.**

T-701 design has been reviewed and the implementation plan has been written/self-reviewed. Implementation has **not started**. `D-059`, `D-060`, `D-061`, and `D-026` remain **PROPOSED**. The next gate is explicit user authorization to execute the T-701 plan. Do not start T-702, promote decisions, merge, or publish automatically.