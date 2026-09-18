# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/t-703-laravel-mcp-projection`
- **Milestone:** `M7 — Conformance / Ecosystem Bridges` — **IN PROGRESS**
- **Last completed/reviewed task:** `T-702 — Adapter author guide`
- **Current task:** `T-703 — Laravel MCP projection` — **IMPLEMENTATION STARTED / TASK 4 COMPLETE**
- **T-701 state:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **T-702 state:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **T-702 design:** `docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md`
- **T-702 implementation plan:** `docs/superpowers/plans/2026-09-16-adapter-author-guide.md`
- **T-702 integration:** fast-forwarded from `feat/t-702-adapter-author-guide` to `main`
- **T-702 verified documentation head:** `04e69ecb8a4d752732f2ab85eaf0f14798944e68`
- **T-702 implementation-head CI:** `#821` / `35150202622` — **7/7 jobs SUCCESS**
- **T-702 merged main head:** `ffd97c9a6608e37a4042d899e079ab751ecc5272`
- **T-702 post-merge main CI:** `#825` / `35299100221` — **7/7 jobs SUCCESS**
- **T-702 browser evidence:** **20 files / 328/328 Vitest + typecheck**
- **T-702 Python conformance:** **47/47 PASS** on CPython 3.12.14
- **T-702 canonical matrix:** **7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR**
- **T-702 contract validation:** **PASS**
- **T-701 verified implementation head:** `2c15515a77d2dd1d79ea970a2811ca0c40191ceb`
- **T-701 external-review code-fix head:** `89a968785a09032cbf3b71f7f60c3f14b76c10ab`
- **T-701 final reviewed PR head:** `b7d2c4aee175da80f0c97d103f915f751c6b0fc7`
- **T-701 review-closure tracking head:** `3777fce830ae2d4a1bcc64925af24563f611ea3e`
- **T-701 decision-promotion head:** `1efe11714e9d3abcd0e8a12fcb90912b23522ad4`
- **T-701 final feature/tracking head:** `5da153c3c0a0f6c38d9b5f01b26899efa24ea949`
- **T-701 merge commit:** `50c8c482165a115b8b3b8cb740f123ecf4203041`
- **T-701 post-merge main CI:** `#809` / `35080877519` — **7/7 jobs SUCCESS**
- **T-701 post-merge browser:** **20 files / 328/328 Vitest + typecheck**
- **T-701 post-merge Python conformance:** **47/47 PASS** on CPython 3.12.14
- **T-701 post-merge canonical matrix:** **7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR**
- **Accepted decisions:** `D-059`, `D-060`, `D-061`, `D-062` — **ACCEPTED**
- **Proposed decisions:** `D-026`, `D-063`, `D-064` — **PROPOSED**
- **T-702 review-only PR:** `#15` — **CLOSED WITHOUT MERGE**
- **T-702 CodeRabbit review:** **1 Minor actionable / 1 resolved / 0 unresolved**
- **T-702 review-fix head:** `d5c67a5ee403cdd6cc5805ac072dc05f6139d309`
- **T-702 review-fix CI:** `#830` / `35299933662` — **7/7 jobs SUCCESS**
- **T-702 post-review main CI:** `#832` / `35300067389` — **7/7 jobs SUCCESS**
- **T-702 decision-promotion head:** `f37955979bf0c45878b449b5dd7c608046d94fb4`
- **T-702 decision-promotion CI:** `#834` / `35302680228` — **7/7 jobs SUCCESS**
- **T-703 design:** `docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md`
- **T-703 decisions:** `D-063`, `D-064` — **PROPOSED**
- **T-703 implementation plan:** `docs/superpowers/plans/2026-09-18-laravel-mcp-projection.md` — **APPROVED**
- **T-703 implementation:** **STARTED — TASK 4 COMPLETE**
- **T-703 Task 1 head:** `407a8a9c7c8a2d1d14db9252e600cf4284cb3175`
- **T-703 Task 1 CI:** `#839` / `35324030173` — **11/11 jobs SUCCESS**
- **T-703 Task 1 bridge matrix:** **4/4 SUCCESS**, each with **1 architecture test / 157 assertions**
- **T-703 design-head CI:** `#837` / `35319627802` — **7/7 jobs SUCCESS**
- **T-703 Task 2 RED head:** `d7881d9f4eed15edc45ca1a1c4260a7134c78864` — `#841` expected FAILURE
- **T-703 Task 2 GREEN head:** `d73811fb3f8a6c24ea47eb04ef3c3acf771dd3e5`
- **T-703 Task 2 CI:** `#842` / `35327114009` — **11/11 jobs SUCCESS**
- **T-703 Task 2 bridge suite:** **9 tests / 171 assertions**, 4/4 bridge matrix SUCCESS
- **T-703 Task 3 RED head:** `624392b0ba03d652100a4c7dbf7a41952afc7ed1` — `#845` expected FAILURE
- **T-703 Task 3 GREEN head:** `5892bb4361a1f829c5b2ebae9185b1d11ae4781a`
- **T-703 Task 3 CI:** `#846` / `35329092370` — **11/11 jobs SUCCESS**
- **T-703 Task 3 bridge suite:** **18 tests / 190 assertions**, 4/4 bridge matrix SUCCESS
- **T-703 Task 4 RED head:** `b664afbf2009b303eb77d7c3effad5dee6b18ecd` — `#849` expected FAILURE
- **T-703 Task 4 GREEN head:** `92034ab0752619b5da7216d3b3cb23452b50409a`
- **T-703 Task 4 CI:** `#850` / `35362116668` — **11/11 jobs SUCCESS**
- **T-703 Task 4 bridge suite:** **28 tests / 236 assertions**, 4/4 bridge matrix SUCCESS
- **Next gate:** T-703 Task 5 — dynamic Laravel MCP server + service-provider wiring only. T-704 remains outside the active task.

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

`D-058` and `D-020` remain ACCEPTED from the T-604 closure. T-702 does not alter that bounded portability conclusion.

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
Review-closure tracking:          3777fce830ae2d4a1bcc64925af24563f611ea3e
Review-closure push validate:     #799 / 35050355079 — 7/7 SUCCESS
Final Python conformance suite:    47/47 PASS on CPython 3.12.14
Final browser suite:               20 files / 328/328 PASS + typecheck
Final harness build:               PASS
Final canonical matrix:            7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
CodeRabbit threads:                4/4 individually rechecked/resolved / 0 unresolved
```

No second full CodeRabbit sweep is claimed; review closure is based on the initial full review, thread-specific reviewer rechecks, and fresh exact-head CI.

## Decision promotion

External review confirmed the implemented T-701 architecture, and the decision-promotion gate accepted the decisions that exactly describe that reviewed implementation:

- `D-059` — **ACCEPTED** — profile + capability applicability; runner-owned canonical selection/applicability/verdicts; raw harness observations only.
- `D-060` — **ACCEPTED** — repo-local one-scenario/one-subprocess JSON protocol, fixed timeout, Python stdlib orchestrator, repo-root command execution.
- `D-061` — **ACCEPTED** — canonical scenario semantics in the registry, execution wiring outside the spec, mandatory positive control, closed four-scenario/two-target browser v1 claim.
- `D-026` — **PROPOSED** independently; provisional binding failure codes remain advisory rather than globally normative.

Decision-promotion evidence:

```text
Decision-promotion head:           1efe11714e9d3abcd0e8a12fcb90912b23522ad4
Decision-promotion PR validate:    #802 / 35058068033 — 7/7 SUCCESS
Tracking-closure head:             5da153c3c0a0f6c38d9b5f01b26899efa24ea949
Tracking-closure push validate:    #803 / 35058313792 — 7/7 SUCCESS
Browser:                           20 files / 328/328 PASS + typecheck
Python conformance tests:          47/47 PASS
Harness build:                     PASS
Canonical matrix:                  7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Contract validation:               PASS
```

The accepted vocabulary remains bounded to the reviewed repo-local T-701 v1 runner. It does not publish a standalone specification, certification program, or global error-code enum.

## Merge / main revalidation

PR #14 was merged from the exact reviewed/tracking head into `main`.

```text
Final feature/tracking head:       5da153c3c0a0f6c38d9b5f01b26899efa24ea949
Merge commit:                      50c8c482165a115b8b3b8cb740f123ecf4203041
Post-merge main validate:          #809 / 35080877519 — 7/7 SUCCESS
Post-merge contract validation:    PASS
Post-merge browser typecheck:      PASS
Post-merge browser Vitest:         20 files / 328/328 PASS
Post-merge Python conformance:     47/47 PASS on CPython 3.12.14
Post-merge harness build:          PASS
Post-merge canonical matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

The main-branch execution checked out exact merge SHA `50c8c482165a115b8b3b8cb740f123ecf4203041`; the canonical runner remained green after integration.

## T-702 implementation

T-702 design and implementation plan:

```text
docs/superpowers/specs/2026-09-16-adapter-author-guide-design.md
docs/superpowers/plans/2026-09-16-adapter-author-guide.md
```

Implemented documentation:

```text
docs/adapters/README.md
docs/adapters/author-guide.md
docs/adapters/conformance.md
docs/adapters/security.md
```

The implementation preserves the approved design boundary:

- the guide is explanatory and explicitly subordinate to canonical contracts, accepted decisions, and executable conformance semantics;
- import/definition, runtime/binding, and surface/projection roles are author-facing responsibility categories, not new core interfaces;
- trusted context, explicit exposure, discovery-vs-invocation, exact-target, no-retarget, deterministic mapping, lifecycle/expiry, fail-closed, and bounded cancellation invariants remain canonical rather than being redefined by examples;
- `docs/adapters/author-guide.md` provides the responsibility matrix and 12-step author workflow;
- `docs/adapters/security.md` makes trust/security constraints part of adapter correctness rather than an appendix;
- `docs/adapters/conformance.md` carries D-059 through D-061 forward exactly: truthful profile/capability claims, runner-owned selection/applicability/verdicts, bounded raw harness observations, current repo-local process boundary, revision-scoped evidence, and bounded compatibility wording;
- Livewire and HTMX are documented as materially different reference techniques for shared portable invariants, not a universal target shape;
- D-026 remains PROPOSED; no global error enum is created;
- D-062 remains PROPOSED; implementation does not promote it;
- T-703 Laravel MCP projection and T-704 OpenAPI import remain outside T-702 and are not started.

### T-702 implementation verification

Verified documentation/navigation head:

```text
04e69ecb8a4d752732f2ab85eaf0f14798944e68
```

Exact-head GitHub Actions evidence:

```text
Validate:                       #821 / 35150202622 — 7/7 SUCCESS
Contract / scripts/validate.py: PASS
PHP matrix:                     4/4 PASS
PHP lint:                       PASS
Browser typecheck:              PASS
Browser Vitest:                 20 files / 328/328 PASS
Python conformance:             47/47 PASS on CPython 3.12.14
Harness build:                  PASS
Canonical runtime matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

The canonical runtime result remained unchanged:

```text
BIND-EXACT-TARGET-EXECUTES       Livewire PASS / HTMX PASS
BIND-EXPIRED-NOT-EXECUTABLE     Livewire PASS / HTMX PASS
BIND-COMPONENT-STALE             Livewire PASS / HTMX NOT_APPLICABLE
BIND-NO-SILENT-RETARGET          Livewire PASS / HTMX PASS
```

## Scope audit

The pre-merge `main..feat/t-702-adapter-author-guide` diff was inspected before integration; the same exact feature head was then fast-forwarded to `main`.

T-702 implementation adds documentation/navigation/tracking only and introduces no semantic changes under:

```text
packages/browser-runtime/src/**
packages/laravel/src/**
spec/0.1/**
conformance/targets/**
scripts/conformance_model.py
scripts/run_conformance.py
```

Additional boundaries remain intact:

- no new dependency;
- no new profile/capability/canonical scenario;
- no T-701 runner/applicability/verdict change;
- no Livewire/HTMX production behavior change;
- no MCP implementation;
- no OpenAPI importer;
- no standalone public-spec extraction;
- no compatibility badge/certification/signing/registry infrastructure;
- D-026 remains PROPOSED;
- D-062 is ACCEPTED as the externally reviewed guide-authority boundary; it does not authorize new adapter semantics.

## Explicit non-goals

T-702 does not create or claim an adapter SDK, universal adapter interface, scaffolding CLI, plugin loader, new RuntimeBinding/BindingDriver abstraction, new Action Definition field, new profile/capability, new canonical scenario, new failure-code enum, compatibility registry, certification program, remote conformance system, MCP implementation, OpenAPI importer, production Livewire/HTMX behavior change, or standalone public-spec extraction.

## T-703 scope/design gate

T-703 design and implementation plan are approved. Implementation has started and Task 4 is complete; no later task has started.

```text
Branch:                   feat/t-703-laravel-mcp-projection
Design:                   docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md
D-063:                    PROPOSED
D-064:                    PROPOSED
Implementation plan:      docs/superpowers/plans/2026-09-18-laravel-mcp-projection.md — APPROVED
Implementation:           STARTED — TASK 4 COMPLETE
Production changes:       NONE
Dependency changes:       NONE
Canonical spec changes:   NONE
T-701 conformance change: NONE
T-704 work:               NOT STARTED
```

Locked design direction:

- maintained `laravel/mcp` rather than an in-house MCP protocol implementation;
- optional `packages/laravel-mcp` bridge with one-way dependency on `packages/laravel`;
- MCP Tools only for v1;
- explicit MCP exposure by exact Action identity;
- only portable/headless action scopes;
- MCP request arguments remain untrusted business input;
- trusted actor/tenant authority remains existing Laravel runtime state;
- namespaced confirmation/idempotency metadata remains non-authoritative until existing server-side verification;
- all business invocation converges on ActionBus and ActionResultNormalizer;
- no expansion of T-701's closed conformance profile.

## Current boundary

**M6 is closed. T-604 is closed. T-701 is closed.**

T-702 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-703 is **IN_PROGRESS / DESIGN APPROVED / PLAN APPROVED / IMPLEMENTATION STARTED / TASK 4 COMPLETE** on `feat/t-703-laravel-mcp-projection`. `D-059`, `D-060`, `D-061`, and `D-062` are **ACCEPTED**; `D-026`, `D-063`, and `D-064` are **PROPOSED**. M7 remains **IN PROGRESS**. T-704 remains TODO. No later task begins automatically.