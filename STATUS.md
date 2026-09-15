# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/executable-conformance-runner`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Last completed/reviewed task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Current task:** `T-701 — Executable conformance runner`
- **T-701 state:** **IN_PROGRESS / IMPLEMENTATION VERIFIED / EXTERNAL REVIEW PENDING**
- **Design:** `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- **Plan:** `docs/superpowers/plans/2026-09-15-executable-conformance-runner.md`
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **Verified implementation CI:** run `#789` / `35016612915` — **7/7 jobs SUCCESS**
- **Proposed decisions:** `D-059`, `D-060`, `D-061` — **PROPOSED**
- **Existing provisional decision:** `D-026` — **PROPOSED**
- **Next gate:** external review of T-701. Do not begin T-702, promote decisions, merge, or publish automatically.

## M6 historical evidence — preserved

M6 established four cumulative HTMX portability proofs:

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

Key closure evidence remains:

```text
T-601 final feature:             e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
T-601 merge:                     96581ff9d12dba5487b4831c3bc146081945decb
T-601 post-merge CI:             34657967629 — 7/7 green

T-602 PR:                        #11
T-602 final feature:             ebad0f04a3b540a5a1536350038d0919ff600ebd
T-602 merge:                     ee9af986f22cba45b05c59059c11e68ac46111fd
T-602 final closure CI:          34702679885 — 7/7 green

T-603 PR:                        #12
T-603 final feature/review:      076108554d6995fea65108ca07c9b64d3994d459
T-603 merge:                     e98c919b90f9f19b58ae56b88e391f1abbb179e7
T-603 post-merge validate:       34758253025 — 7/7 green
T-603 real-browser fixture:      34758253003 — 8/8 Playwright

T-604 PR:                        #13
T-604 implementation head:      9d49c22ac2127c7ade6e235fae488f87490feb43
T-604 implementation validate:  34793229849 — 7/7 green
T-604 shared matrix:             22/22 — 11 Livewire + 11 HTMX
T-604 browser:                   19 files / 319/319 + typecheck
T-604 fresh T-603 regression:   job 103821380728 — 8/8 real Chromium
T-604 review-fix validate:      34803374097 — 7/7 green
T-604 decision-promotion CI:    34806672450 — 7/7 green
T-604 merge:                    2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
T-604 post-merge validate:      34806790431 — 7/7 green
```

`D-058` and `D-020` are ACCEPTED from the T-604 closure. The bounded portability conclusion remains unchanged: Livewire and HTMX are materially different bindings whose common fail-closed `BindingDriver` overlap has shared executable proof; that did not itself publish a standalone cross-framework specification.

## T-701 implemented boundary

T-701 converts the approved subset of canonical runtime binding semantics into a repo-local executable conformance runner. It does not turn the runner into a public standard, SDK, certification system, or runtime framework.

### Profile, targets and applicability

```text
profile: runtime-binding/driver

targets:
  browser/livewire  capabilities=[lifecycle.component]
  browser/htmx      capabilities=[]
```

Canonical executable scenarios:

```text
BIND-EXACT-TARGET-EXECUTES       Livewire PASS / HTMX PASS
BIND-EXPIRED-NOT-EXECUTABLE     Livewire PASS / HTMX PASS
BIND-COMPONENT-STALE             Livewire PASS / HTMX NOT_APPLICABLE
BIND-NO-SILENT-RETARGET          Livewire PASS / HTMX PASS
```

Verified canonical result:

```text
7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE
```

`BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` remain documented rather than executable in T-701 v1.

### Runner authority

- profiles define atomic conformance claim boundaries;
- capabilities define conditional applicability inside a profile;
- target manifests cannot choose an ad-hoc scenario allowlist;
- mandatory profile scenarios cannot be adapter-skipped;
- `NOT_APPLICABLE` is runner-owned and is determined before process execution;
- harnesses emit raw observations only;
- the Python runner alone compares canonical expectations and decides `PASS` / `FAIL`;
- process/protocol failures become `ERROR`;
- every claimed profile requires a mandatory positive executable control;
- `recommendedCode` remains advisory and is not a normative verdict field.

### Process protocol

```text
canonical registry
      ↓
profile/capability selection
      ↓
Python stdlib runner
   /             \
fresh Livewire   fresh HTMX
subprocess        subprocess
   \             /
    raw observations
          ↓
canonical evaluator
          ↓
PASS / FAIL / ERROR / NOT_APPLICABLE
```

One target/scenario pair uses one fresh subprocess. Request input is one JSON document on stdin. Successful harness output is exactly one protocol JSON document on stdout. stderr is diagnostic-only. Expected results are not sent to harnesses. The fixed production timeout is 10 seconds. Commands are argv arrays and the Python runner uses `shell=False`.

Runner process exit semantics:

```text
0 → all applicable scenarios PASS
1 → at least one FAIL and no ERROR
2 → at least one ERROR
```

### Semantic vs execution ownership

Canonical scenario semantics remain in:

```text
spec/0.1/fixtures/conformance-scenarios.json
```

Target process command/profile/capability wiring remains under repo-local `conformance/targets/` and outside the spec.

`scripts/validate.py` validates structural consistency only and never executes harnesses. Runtime execution/verdicts are owned by `scripts/run_conformance.py`.

### T-604 reuse boundary

T-701 extracted Vitest-free controlled target builders under `packages/browser-runtime/conformance/support/`. The T-604 package tests now use those same builders through thin adapters, so the package regression and process harnesses do not maintain separate Livewire/HTMX behavioral models.

No semantic change was made under `packages/browser-runtime/src/**`.

## T-701 implementation sequence

```text
Task 1 model/evaluator:                8351954b95b94902ca91f3d0d8fe78c0d6675a49
Task 2 process runner/protocol:        ecc14a0a9ff571e9c0de20fba7e3fc2f43387a13
Task 3 shared browser target support:  706a476b5178e9dcbfb9af942446f810cdcc470c
Task 4 Livewire process harness:       9563299aec93508b7275a44efe82325f4a69fa26
Task 5 HTMX process harness:           7ef086d7547837331ca5662ebcbce49ce7b1f44a
Task 6 canonical config/manifests:     65e978393b55ffa2f908cb1518f9b768c9e3fbc3
Task 7 CI/documentation integration:   2c15515a77d2dd1d79ea970a2811ca0c40191ceb
```

Notable implementation deltas found by verification and kept intentionally narrow:

- `scripts/run_conformance.py` received a minimal direct-script import bootstrap because the approved command `python scripts/run_conformance.py` otherwise failed before runner execution;
- Task 6 regression cases were added in `scripts/tests/test_conformance_task6.py` rather than expanding the already-large model test file;
- Task 7 widened unittest discovery from `test_conformance_*.py` to `test_*conformance*.py` because the former silently omitted `test_run_conformance.py`;
- enabling those real runner tests exposed two old test-only typos (`diagostics`, `presult`), corrected without changing runner production behavior.

## Verification evidence

Bound to exact implementation head `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`:

```text
GitHub Actions validate:           #789 / 35016612915 — 7/7 jobs SUCCESS
contract / scripts/validate.py:     PASS
browser TypeScript typecheck:      PASS
browser Vitest:                    20 files / 328/328 PASS
Python runtime:                    CPython 3.12.14
model + runner unit tests:         45/45 PASS
conformance harness build:         PASS
canonical runtime matrix:          7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

Negative evidence includes expiry zero-dispatch, exact-target stale zero-dispatch and equivalent-replacement zero replacement dispatch. HTMX component-stale is runner-owned N/A and is not spawned. Advisory `errorCode` mismatch/missing data does not change a normative PASS when the canonical observation fields match.

## Scope audit

Approved design diff base:

```text
05b020c94279c295068895a8163a892549327d3f
```

Full implementation review confirms:

- runner/model/test files are under `scripts/**`;
- canonical metadata/manifests are under `spec/0.1/fixtures/conformance-scenarios.json` and `conformance/**`;
- browser conformance infrastructure is under `packages/browser-runtime/conformance/**` and test support;
- package change is limited to the conformance build script/config;
- the existing seven-job workflow is extended rather than adding a job;
- no `packages/browser-runtime/src/**` semantic production change;
- no `packages/laravel/src/**` production change;
- no T-603 fixture behavior change;
- no standalone spec extraction;
- no Python/npm dependency expansion for T-701;
- no T-702/T-703/T-704 implementation.

## Needs decision

The following decisions remain intentionally unresolved at the external-review handoff:

- `D-059` — **PROPOSED** — profile + capability applicability; runner-owned canonical selection/verdicts; raw harness observations only.
- `D-060` — **PROPOSED** — one-scenario/one-subprocess JSON process protocol with Python stdlib orchestrator and fixed timeout.
- `D-061` — **PROPOSED** — canonical scenario semantics stay in the registry; execution wiring stays outside the spec; positive controls are required; v1 claims only browser `runtime-binding/driver`.
- `D-026` — **PROPOSED** independently; provisional binding failure codes remain advisory rather than globally normative.

Passing implementation tests does not promote these decisions. Promotion requires a later explicit gate after external review.

## Explicit non-goals

T-701 v1 does not create or claim:

- a public conformance SDK/package or certification program;
- standalone SurfaceRelay specification extraction;
- Laravel/PHP conformance target coverage;
- binding lookup, driver-registry or action-availability executable conformance;
- Trust/confirmation/idempotency conformance;
- Output/Projection conformance;
- real-browser orchestration inside the runner;
- remote targets, parallelism, retries, watch mode, plugin discovery, or a generic assertion DSL;
- automatic work on T-702, T-703 or T-704.

## Current boundary

**M6 is closed. T-604 is closed.**

T-701 implementation and automated verification are complete, but T-701 is **not yet externally reviewed, decision-promoted, merged, or main-revalidated**. `D-026`, `D-059`, `D-060`, and `D-061` remain **PROPOSED**. The next gate is external review of T-701. Do not start T-702, promote decisions, merge, or publish automatically.