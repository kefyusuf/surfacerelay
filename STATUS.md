# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/executable-conformance-runner`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Last completed task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Current task:** `T-701 — Executable conformance runner`
- **T-701 state:** **IN_PROGRESS / DESIGN APPROVED / IMPLEMENTATION NOT STARTED**
- **Design:** `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- **Design branch base:** `main@67474d98152c7883af6106c92ca327bcd2e87307`
- **Proposed decisions:** `D-059`, `D-060`, `D-061` — **PROPOSED**
- **Existing provisional decision:** `D-026` — remains **PROPOSED**
- **Next gate:** explicit user review of the committed T-701 design artifact; do not create an implementation plan or start implementation automatically.

## M6 delivered proof

M6 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED** and remains the evidence baseline for T-701.

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

T-604 closure evidence remains:

```text
Implementation head:            9d49c22ac2127c7ade6e235fae488f87490feb43
Implementation validate:        34793229849 — 7/7 green
Shared matrix:                  22/22 — 11 Livewire + 11 HTMX
Full browser runtime:           19 files / 319/319 + typecheck
Fresh T-603 regression:         job 103821380728 — 8/8 real Chromium
CodeRabbit:                     LOW risk; 2/2 actionable resolved / 0 unresolved
Decision promotion:             bfcbde90ba2892f2e192a1df8d5c7c84310b23c0
Decision PR validate:           34806672450 — 7/7 green
D-058 / D-020:                 ACCEPTED
Merge commit:                   2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
Post-merge main validate:       34806790431 — 7/7 green
Final M6 closure main:          67474d98152c7883af6106c92ca327bcd2e87307
Final M6 closure validate:      34806964285 — 7/7 green
```

The M6 portability claim remains bounded: HTMX and Livewire are materially different, while the generic SurfaceRelay binding envelope and common fail-closed invariants survive those differences. M6 does not authorize standalone-spec extraction.

## T-701 approved design boundary

T-701 turns a bounded subset of canonical runtime conformance semantics into a repo-local executable runner without turning the runner into a public standard or runtime framework.

### Claimed profile and targets

T-701 v1 claims exactly:

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

Approved design rules:

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

One target/scenario pair uses one fresh subprocess. Request is one JSON document on stdin; stdout is exactly one observation JSON; stderr is diagnostics. The request does not contain expected results. Fixed v1 timeout is 10 seconds.

Runner exit semantics are designed as:

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

Repo-local target command/profile/capability wiring remains outside the spec, under T-701 conformance infrastructure.

`scripts/validate.py` remains responsible for structural integrity; `scripts/run_conformance.py` will be responsible for runtime execution and verdicts. The runner will not import Livewire, HTMX, or Laravel runtime implementations and will not act as a package/build manager.

### Test/harness reuse boundary

T-701 should extract reusable Vitest-free controlled target setup where practical so T-604 tests and T-701 harnesses do not drift into two independent Livewire/HTMX behavioral models.

Production browser runtime files under `packages/browser-runtime/src/**` are not expected to require changes for T-701 v1.

## Needs decision

The following T-701 decisions are intentionally unresolved until implementation evidence and external review exist:

- `D-059` — **PROPOSED** — conformance authority uses profile + capability; runner owns canonical scenario selection and PASS/FAIL, harnesses emit raw observations only.
- `D-060` — **PROPOSED** — language-neutral one-scenario/one-subprocess JSON protocol with Python stdlib orchestrator, stderr diagnostics and fixed timeout.
- `D-061` — **PROPOSED** — canonical semantics stay in the scenario registry, execution wiring stays outside the spec, and every executable claimed profile requires a mandatory positive scenario; v1 claims only browser `runtime-binding/driver`.
- `D-026` — remains **PROPOSED** independently; recommended binding failure codes are not made globally normative by T-701.

No implementation should promote D-059/D-060/D-061 automatically merely because tests pass. Promotion requires a later explicit decision gate after executable verification and external review.

## Required later implementation evidence

The approved design requires at minimum:

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

The T-701 design is approved and written, but implementation has **not started**. `D-059`, `D-060`, and `D-061` are **PROPOSED**, not accepted.

The next gate is the user's review of `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`. Only after that explicit approval may the workflow move to the implementation-planning gate. Do not implement T-701 and do not begin T-702 automatically.