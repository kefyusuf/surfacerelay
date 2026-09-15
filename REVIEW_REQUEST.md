# External Review Request — T-701 Executable Conformance Runner

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/executable-conformance-runner`
- **Task:** `T-701 — Executable conformance runner`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Review state:** **IMPLEMENTATION VERIFIED / EXTERNAL REVIEW PENDING**
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **Push validate:** run `#789` / `35016612915` — **7/7 jobs SUCCESS**
- **Conformance unit tests:** **45/45 PASS** on CPython 3.12.14
- **Browser regression:** **20 files / 328/328 Vitest + TypeScript typecheck**
- **Canonical runtime matrix:** **7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR**
- **Decisions:** `D-026`, `D-059`, `D-060`, `D-061` remain **PROPOSED**
- **Merge:** not performed
- **T-702/T-703/T-704:** not started

This document is a review handoff. It is not a decision-promotion record and not a merge record.

## Design / plan

```text
docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md
docs/superpowers/plans/2026-09-15-executable-conformance-runner.md
```

T-701 implements a bounded repo-local executable conformance runner for the shared browser `runtime-binding/driver` profile. It does not create a public conformance SDK, standalone specification, certification program, or generalized runtime framework.

## Scope under review

The implementation consists of five connected layers:

1. **Pure Python conformance model**
   - validates executable runtime scenarios and target claims;
   - selects canonical scenarios by profile/capability;
   - evaluates normative raw observations;
   - aggregates exit semantics.

2. **Strict Python process runner**
   - one target/scenario pair per fresh subprocess;
   - argv command execution with `shell=False`;
   - fixed 10-second timeout;
   - one JSON request on stdin;
   - exactly one protocol JSON response on stdout;
   - stderr diagnostics only;
   - protocol/infrastructure problems become `ERROR`.

3. **Vitest-free browser target support and process harnesses**
   - shared controlled Livewire/HTMX target builders;
   - one shared raw-observation executor;
   - separate thin Livewire and HTMX process entrypoints;
   - T-604 package tests reuse the same controlled target support.

4. **Canonical scenario registry + repo-local target manifests**
   - semantic truth remains in `spec/0.1/fixtures/conformance-scenarios.json`;
   - command/profile/capability wiring remains under `conformance/targets/`;
   - `scripts/validate.py` checks structure only and never executes harnesses.

5. **Existing CI integration**
   - no eighth workflow job;
   - existing browser job now also runs Python conformance unit tests, harness build and canonical runtime matrix.

## Explicit non-goals

Review should reject accidental expansion into any of the following because they are outside T-701 v1:

- public conformance package/SDK or certification API;
- standalone SurfaceRelay specification extraction;
- Laravel/PHP conformance target;
- binding lookup, driver-registry or action-availability executable profiles;
- Trust/confirmation/idempotency conformance;
- Output/Projection conformance;
- real-browser orchestration inside the runner;
- remote targets, persistent workers, parallelism, retries, watch mode or plugin discovery;
- generic assertion DSL;
- semantic production changes under `packages/browser-runtime/src/**`;
- implementation of T-702/T-703/T-704.

## Canonical v1 profile and targets

```text
profile: runtime-binding/driver

targets:
  browser/livewire
    capabilities: [lifecycle.component]

  browser/htmx
    capabilities: []
```

Canonical executable scenarios:

| Scenario | Livewire | HTMX | Normative observation intent |
|---|---|---|---|
| `BIND-EXACT-TARGET-EXECUTES` | applicable | applicable | returns; framework dispatch 1; replacement dispatch 0 |
| `BIND-EXPIRED-NOT-EXECUTABLE` | applicable | applicable | throws; framework dispatch 0 |
| `BIND-COMPONENT-STALE` | applicable | runner-owned N/A | throws; framework dispatch 0 |
| `BIND-NO-SILENT-RETARGET` | applicable | applicable | throws; framework dispatch 0; replacement dispatch 0 |

Verified full result:

```text
7 PASS, 0 FAIL, 0 ERROR, 1 NOT_APPLICABLE
```

HTMX `BIND-COMPONENT-STALE` is determined `NOT_APPLICABLE` before process execution because HTMX does not claim `lifecycle.component`. The harness cannot self-report N/A.

## Runner authority boundary

The central architecture question is whether the implementation preserves the authority split from D-059/D-061 without prematurely accepting those decisions.

Implemented behavior:

```text
canonical scenario registry
+
target profile claims
+
target capabilities
=
runner-selected execution set
```

The target cannot provide an ad-hoc scenario allowlist. Mandatory profile scenarios cannot be skipped. A capability can add applicability obligations but cannot remove mandatory scenarios.

Harnesses emit only bounded raw facts:

```text
termination: returned | threw
errorCode?: string
frameworkDispatchCount: non-negative integer
replacementDispatchCount?: non-negative integer
```

Harnesses do not emit `passed`, `conformant`, `failClosed`, `retargetPrevented`, `NOT_APPLICABLE`, or other interpretive verdict fields.

Only the Python runner compares canonical expectations and decides `PASS` or `FAIL`.

## Process protocol boundary

One selected applicable case gets one new child process:

```text
one target × one scenario = one subprocess
```

Request envelope contains routing/clock information only; expected results are deliberately absent.

Process contract:

- stdin: exactly one JSON request document;
- stdout: exactly one protocol JSON response document;
- stderr: diagnostics only;
- child exit `0`: trustworthy protocol observation was produced;
- non-zero child exit: infrastructure `ERROR`;
- timeout: `ERROR`;
- empty/invalid/extra stdout: `ERROR`;
- wrong echoed protocol/request/scenario/target/profile: `ERROR`.

Expected production exceptions are observations with `termination=threw`; they are not represented by non-zero child process exit.

## Positive-control / fail-closed boundary

Every claimed executable profile must contain a mandatory positive scenario. T-701 uses `BIND-EXACT-TARGET-EXECUTES` for this purpose.

This prevents an implementation that simply rejects every invocation from appearing conformant merely because all negative scenarios fail closed.

Negative cases additionally prove dispatch absence:

- expired binding → zero framework dispatch;
- stale exact target → zero framework dispatch;
- equivalent replacement target → zero framework dispatch and zero replacement dispatch.

## Advisory error-code boundary

`recommendedCode` remains advisory. Canonical expectations compare only the bounded normative observation fields.

A missing or different raw `errorCode` cannot by itself flip an otherwise matching case to FAIL. This is deliberate so T-701 does not silently promote `D-026` into a globally normative error-code enum.

## Changed-file summary

Against approved design diff base `05b020c94279c295068895a8163a892549327d3f`, the implementation/review scope is limited to:

```text
.github/workflows/validate.yml
CONFORMANCE.md
TASKS.md / STATUS.md / REVIEW_REQUEST.md tracking
conformance/**
docs/superpowers/plans/2026-09-15-executable-conformance-runner.md
packages/browser-runtime/conformance/**
packages/browser-runtime/tests/binding-driver-conformance-observation.test.ts
packages/browser-runtime/tests/support/**
packages/browser-runtime/tsconfig.conformance.json
packages/browser-runtime/package.json
scripts/conformance_model.py
scripts/run_conformance.py
scripts/tests/**conformance**
scripts/validate.py
spec/0.1/fixtures/conformance-scenarios.json
```

Scope audit confirms no semantic changes under:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
examples/htmx-prep-list/**
```

There is no T-701 dependency expansion in `packages/browser-runtime/package-lock.json` or `requirements-dev.txt`.

## TDD / implementation progression

```text
Task 1 — model/evaluator:               8351954b95b94902ca91f3d0d8fe78c0d6675a49
Task 2 — subprocess runner:             ecc14a0a9ff571e9c0de20fba7e3fc2f43387a13
Task 3 — shared target support:         706a476b5178e9dcbfb9af942446f810cdcc470c
Task 4 — Livewire process harness:      9563299aec93508b7275a44efe82325f4a69fa26
Task 5 — HTMX process harness:          7ef086d7547837331ca5662ebcbce49ce7b1f44a
Task 6 — canonical config/validation:   65e978393b55ffa2f908cb1518f9b768c9e3fbc3
Task 7 — CI + conformance docs:         2c15515a77d2dd1d79ea970a2811ca0c40191ceb
```

Observed RED evidence included missing model/process modules, missing Vitest-free target builders, missing process entrypoints and an initially non-executable direct Python runner command. Each was resolved in the owning slice before progressing.

Task 7 also exposed a verification gap in the written unittest discovery pattern: `test_conformance_*.py` omitted `test_run_conformance.py`. The CI pattern was widened to `test_*conformance*.py`, and the now-executed runner suite exposed two existing test-only typos that were corrected without production behavior changes.

## Verification evidence bound to implementation head

Exact head:

```text
2c15515a77d2dd1d79ea970a2811ca0c40191ceb
```

Push workflow:

```text
validate run:        #789 / 35016612915
workflow jobs:       7 total
result:              7/7 SUCCESS
```

Browser job evidence:

```text
Node:                         22.23.2
TypeScript typecheck:         PASS
Vitest:                       20 files / 328/328 PASS
Python:                       CPython 3.12.14
Conformance unit tests:       45/45 PASS
Conformance harness build:    PASS
Canonical runner:             7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

Contract job evidence:

```text
python scripts/validate.py    PASS
```

The existing T-603 real-browser evidence remains separate from T-701 and is not reclassified as runner-owned evidence.

## Notable implementation deltas for reviewer attention

These deviations from the literal file-by-file plan were discovered during executable verification and are intentionally narrow:

1. Task 6 added regression cases in `scripts/tests/test_conformance_task6.py` instead of appending them to `test_conformance_model.py`.
2. `scripts/run_conformance.py` received a minimal repository-root bootstrap so the plan's direct invocation `python scripts/run_conformance.py` works; runner selection/verdict semantics were not changed by that fix.
3. Task 7 uses `test_*conformance*.py` instead of the plan's `test_conformance_*.py` because the latter does not include `test_run_conformance.py`.
4. Two pre-existing runner-test typos were fixed once that test file actually entered CI.

Review should determine whether these are acceptable plan corrections or require consolidation before decision promotion.

## Review questions

Please review specifically:

1. **Runner authority:** Is canonical selection/verdict ownership kept entirely in the runner, with no harness self-certification path?
2. **Protocol strictness:** Do malformed stdout, wrong envelope echoes, non-zero exit and timeout fail closed as infrastructure `ERROR` rather than semantic FAIL?
3. **Capability applicability:** Is HTMX component-stale correctly runner-owned N/A before spawn, without capabilities becoming scenario-skipping controls?
4. **Positive control:** Does the mandatory exact-execution case adequately prevent an always-reject target from claiming the profile?
5. **Advisory codes:** Is ignoring `errorCode` for normative verdicts appropriate while D-026 remains PROPOSED?
6. **No-retarget evidence:** Does the raw dispatch/replacement-dispatch evidence sufficiently prove that old bindings do not execute equivalent replacements?
7. **Fresh-process isolation:** Does one scenario/one subprocess provide the intended state-isolation and crash attribution boundary without unnecessary framework abstraction?
8. **Scope containment:** Did implementation stay inside the approved v1 boundary with no production-driver semantic change, dependency expansion or premature standalone-spec/public-SDK work?
9. **Documentation claims:** Are T-603, T-604 and T-701 evidence layers kept distinct and are Trust/Output/Projection claims correctly excluded?
10. **Plan corrections:** Are the four implementation deltas listed above acceptable and sufficiently documented?

## Decision state

The implementation provides evidence for later decision review but does **not** promote decisions itself:

- `D-026` — **PROPOSED**
- `D-059` — **PROPOSED**
- `D-060` — **PROPOSED**
- `D-061` — **PROPOSED**

A favorable implementation review does not automatically change those statuses. Decision promotion requires a later explicit gate and should be followed by fresh CI evidence before merge.

## Merge / next-task boundary

At this handoff:

- no merge is authorized;
- no architecture decision promotion is authorized;
- T-702 is not authorized;
- T-703/T-704 are not authorized;
- no standalone spec or public conformance package is authorized.

After external review, any review fixes must be applied and reverified first. Decision promotion, merge, post-merge main revalidation and starting T-702 each require their own explicit gate.
