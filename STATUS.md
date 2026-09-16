# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/executable-conformance-runner`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Last completed/reviewed task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Current task:** `T-701 — Executable conformance runner`
- **T-701 state:** **IN_PROGRESS / IMPLEMENTATION VERIFIED / EXTERNAL REVIEW COMPLETE / DECISION GATE PENDING**
- **Design:** `docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md`
- **Plan:** `docs/superpowers/plans/2026-09-15-executable-conformance-runner.md`
- **Approved design diff base:** `05b020c94279c295068895a8163a892549327d3f`
- **Verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **External-review code-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **Final reviewed PR head:** `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`
- **Final reviewed PR CI:** `#798` / `35049891609` — **SUCCESS, 7 jobs total**
- **CodeRabbit:** **4/4 threads resolved / 0 unresolved**; one original HTMX finding was rechecked and confirmed invalid rather than implemented
- **Proposed decisions:** `D-059`, `D-060`, `D-061` — **PROPOSED**
- **Existing provisional decision:** `D-026` — **PROPOSED**
- **Next gate:** explicit T-701 decision promotion. Merge and T-702 remain separate later gates.

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

Key closure evidence:

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

`D-058` and `D-020` remain ACCEPTED from the T-604 closure. T-701 does not alter that bounded portability conclusion.

## T-701 implemented boundary

T-701 provides a repo-local executable conformance runner for a deliberately closed v1 claim:

```text
profile: runtime-binding/driver

targets:
  browser/livewire  capabilities=[lifecycle.component]
  browser/htmx      capabilities=[]

executable runtime scenarios:
  BIND-EXACT-TARGET-EXECUTES
  BIND-EXPIRED-NOT-EXECUTABLE
  BIND-COMPONENT-STALE
  BIND-NO-SILENT-RETARGET
```

Applicability and verified result:

```text
BIND-EXACT-TARGET-EXECUTES       Livewire PASS / HTMX PASS
BIND-EXPIRED-NOT-EXECUTABLE     Livewire PASS / HTMX PASS
BIND-COMPONENT-STALE             Livewire PASS / HTMX NOT_APPLICABLE
BIND-NO-SILENT-RETARGET          Livewire PASS / HTMX PASS

7 PASS / 0 FAIL / 0 ERROR / 1 NOT_APPLICABLE
```

`BIND-ID-UNKNOWN`, `BIND-DRIVER-UNKNOWN`, and `BIND-ACTION-VERSION-UNAVAILABLE` remain documented rather than executable in T-701 v1.

### Runner/process authority

- Python runner owns canonical scenario selection, capability applicability and `PASS` / `FAIL` verdicts;
- v1 validation rejects missing or unexpected executable runtime scenario IDs and target IDs;
- target manifests cannot provide an ad-hoc scenario allowlist;
- HTMX component-stale is runner-owned N/A before child-process spawn;
- harnesses emit bounded raw observations only;
- every claimed profile has a mandatory positive control;
- one applicable target/scenario pair uses one fresh subprocess;
- stdin is one JSON request, stdout exactly one protocol JSON response, stderr diagnostics only;
- production timeout is fixed at 10 seconds;
- commands are argv arrays, use `shell=False`, and execute from repository `ROOT` so repo-relative harness paths are caller-cwd independent;
- `recommendedCode` / raw `errorCode` remain advisory and do not independently flip a verdict;
- `scripts/validate.py` validates structure/configuration and never executes harnesses.

Canonical semantic truth remains in `spec/0.1/fixtures/conformance-scenarios.json`; target command/profile/capability wiring remains repo-local under `conformance/targets/`.

T-701 reuses Vitest-free controlled Livewire/HTMX target builders with T-604 so package tests and process harnesses do not maintain separate behavioral models. No semantic production change was made under `packages/browser-runtime/src/**`.

## Implementation and verification evidence

Implementation sequence:

```text
Task 1 model/evaluator:                8351954b95b94902ca91f3d0d8fe78c0d6675a49
Task 2 process runner/protocol:        ecc14a0a9ff571e9c0de20fba7e3fc2f43387a13
Task 3 shared browser target support:  706a476b5178e9dcbfb9af942446f810cdcc470c
Task 4 Livewire process harness:       9563299aec93508b7275a44efe82325f4a69fa26
Task 5 HTMX process harness:           7ef086d7547837331ca5662ebcbce49ce7b1f44a
Task 6 canonical config/manifests:     65e978393b55ffa2f908cb1518f9b768c9e3fbc3
Task 7 CI/documentation integration:   2c15515a77d2dd1d79ea970a2811ca0c40191ceb
Task 8 review-prep tracking:           041dc9126f63225de0338bdb557446906da78c62
```

Implementation-head verification:

```text
GitHub Actions validate:           #789 / 35016612915 — 7/7 jobs SUCCESS
browser Vitest:                    20 files / 328/328 PASS + typecheck
Python conformance tests:          45/45 PASS
conformance harness build:         PASS
canonical matrix:                  7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
contract / scripts/validate.py:     PASS
```

## External review closure

PR `#14 — feat(conformance): add executable browser conformance runner` received a CodeRabbit external review with four actionable threads.

Review handling:

1. **HTMX replacement identity** — not changed. D-053 requires a replacement target to receive a new opaque `sourceId`; CodeRabbit re-ran its analysis, confirmed the original suggestion invalid, and resolved the thread.
2. **Review handoff concision** — shortened in `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`; CodeRabbit confirmed and resolved the thread.
3. **Closed v1 completeness** — fixed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` with a dedicated v1 validation layer plus regression tests; CodeRabbit confirmed and resolved the thread.
4. **Repo-relative harness cwd** — fixed in `89a968785a09032cbf3b71f7f60c3f14b76c10ab` with `cwd=ROOT` plus regression coverage; CodeRabbit confirmed and resolved the thread.

Executable review evidence:

```text
External-review RED:              #794 / 35049387890 — FAILURE as intended on new review regressions
Correctness-fix head:             89a968785a09032cbf3b71f7f60c3f14b76c10ab
Correctness-fix validate:         #796 / 35049689721 — SUCCESS
Final reviewed PR head:           b7d2c4aee175da80f0c97d103f915f751c6b0fc7
Final reviewed PR validate:       #798 / 35049891609 — SUCCESS, 7 jobs total
Final Python conformance suite:    47/47 PASS on CPython 3.12.14
Final browser suite:               20 files / 328/328 PASS + typecheck
Final harness build:               PASS
Final canonical matrix:            7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
CodeRabbit threads:                4/4 individually rechecked/resolved / 0 unresolved
```

No second full CodeRabbit sweep is claimed; review closure is based on the initial full review, thread-specific reviewer rechecks, and fresh exact-head CI.

## Scope audit

Approved design diff base: `05b020c94279c295068895a8163a892549327d3f`.

Review confirms:

- no semantic changes under `packages/browser-runtime/src/**`;
- no `packages/laravel/src/**` production changes;
- no T-603 fixture behavior changes;
- no standalone spec extraction;
- no Python/npm dependency expansion for T-701;
- no T-702/T-703/T-704 implementation;
- PR #14 remains open and unmerged after external-review closure.

## Needs decision

The reviewed implementation supplies evidence for the next decision gate, but does not promote decisions automatically:

- `D-059` — **PROPOSED** — profile + capability applicability; runner-owned canonical selection/verdicts; raw harness observations only.
- `D-060` — **PROPOSED** — one-scenario/one-subprocess JSON protocol with Python stdlib orchestrator and fixed timeout.
- `D-061` — **PROPOSED** — canonical scenario semantics in the registry, execution wiring outside the spec, mandatory positive control, closed browser `runtime-binding/driver` v1 claim.
- `D-026` — **PROPOSED** independently; provisional binding failure codes remain advisory rather than globally normative.

## Explicit non-goals

T-701 v1 does not create or claim a public conformance SDK/certification system, standalone specification extraction, Laravel/PHP conformance target, Trust/Output/Projection conformance, real-browser orchestration in the runner, remote targets, retries, parallelism, watch mode, plugin discovery, generic assertion DSL, or automatic T-702/T-703/T-704 work.

## Current boundary

**M6 is closed. T-604 is closed.**

T-701 implementation and external review are complete, but T-701 is **not yet decision-promoted, merged, or main-revalidated**. `D-026`, `D-059`, `D-060`, and `D-061` remain **PROPOSED**. The next gate is explicit T-701 decision promotion. Merge, T-702/T-703/T-704, and publication remain separate later gates.
