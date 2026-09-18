# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

> Historical per-task evidence through T-505 is preserved in `docs/archive/TASKS-through-T505-preclosure.md`. Design specs, implementation plans, `STATUS.md`, `REVIEW_REQUEST.md`, PR discussions, and Git history contain detailed evidence for later tasks.

## M0 — Contract Foundation — DONE

- T-001 through T-005 — DONE.

## M1 — Laravel Kernel — DONE

- T-101 through T-110 — DONE.

## M1.1 — Hardening — DONE / REVIEWED

- Reviewed baseline: `11e7348cbee6f69fa8e502308f6db262bf78e271`.

## M2 — Livewire Binding — DONE / REVIEWED

- T-201 through T-204 — DONE / REVIEWED.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

- T-301 through T-305 — DONE / REVIEWED / MERGED as applicable.

## M4 — Production Trust Controls — DONE / REVIEWED / MERGED

- T-401 through T-404 — DONE / REVIEWED.

## M5 — Filament Vertical — DONE / REVIEWED / MERGED / MAIN REVALIDATED

- T-501 through T-505 — DONE / REVIEWED / MERGED / MAIN REVALIDATED.

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

## M6 — HTMX Portability Proof — DONE / REVIEWED / MERGED / MAIN REVALIDATED

### T-601 — Explicit HTMX binding descriptor — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Decision:                    D-053 — ACCEPTED
Final feature head:          e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Merge commit:                96581ff9d12dba5487b4831c3bc146081945decb
Post-merge CI:               34657967629 — 7/7 green
Browser on main:             174/174 + typecheck
```

### T-602 — HTMX browser driver — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Pull request:                      #11
Decisions:                         D-054 / D-055 / D-056 — ACCEPTED
Final feature head:                ebad0f04a3b540a5a1536350038d0919ff600ebd
Merge commit:                      ee9af986f22cba45b05c59059c11e68ac46111fd
Final closure main:                83df122d84f6881a4a3541bd5c72e1108e5d1e48
Final closure CI:                  34702679885 — 7/7 green
Browser on final closure main:     17 files / 297/297 + typecheck
CodeRabbit:                        2 actionable / 2 resolved / 0 unresolved
```

### T-603 — Non-Laravel HTMX fixture app — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Pull request:                    #12 — test(htmx): add real non-Laravel fixture proof
Decision:                        D-057 — ACCEPTED
Final feature/review head:       076108554d6995fea65108ca07c9b64d3994d459
Merge commit:                    e98c919b90f9f19b58ae56b88e391f1abbb179e7
Post-merge main validate:        34758253025 — 7/7 green
Post-merge main fixture:         34758253003 — 8/8 Playwright
Post-merge browser:              17 files / 297/297 + typecheck
CodeRabbit threads:              4/4 actionable resolved / 0 unresolved
```

T-603 proved the HTMX adapter against a real non-Laravel Node application, real `htmx.org@2.0.10`, real Chromium DOM/network execution and the existing `prep_list.add_item@1` ActionDefinition.

### T-604 — Shared conformance against Livewire + HTMX — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-13-binding-driver-conformance-design.md
docs/superpowers/plans/2026-09-14-binding-driver-conformance.md
```

Verified implementation boundary:

- one shared executable 11-case matrix runs unchanged against production `LivewireBrowserDriver` and `HtmxBrowserDriver` through thin test-only adapters;
- shared assertions cover fail-closed target validation, expiry, Action-input mappability, exact-target stale/no-retarget behavior, dispatch counts and already-aborted no-dispatch cancellation;
- equivalent replacement identities receive zero dispatch from an old binding;
- framework-specific target shapes, lifecycles, runtime APIs, local errors, cancellation mechanisms and successful return values remain driver-owned.

TDD / executable evidence:

```text
Livewire RED head:              ae82c3d3bec6918a45932c3b11278b64b9ffe9d4
Livewire RED validate:          34793105308 — browser tests failed after typecheck passed
Livewire GREEN head:            bbb594f91ce2983adf0652fdad12d0fc05ce7509
Livewire GREEN validate:        34793156495 — browser job green
HTMX RED head:                  7b04d5e04c6d97e51039c680ee78cba374e78413
HTMX RED validate:              34793193056 — browser tests failed after typecheck passed
Implementation head:            9d49c22ac2127c7ade6e235fae488f87490feb43
Implementation validate:        34793229849 — 7/7 green
Shared matrix:                  22/22 — 11 Livewire + 11 HTMX
Full browser-runtime suite:     19 files / 319/319 + typecheck
Fresh T-603 regression:         job 103821380728 — 8/8 real Chromium
```

External review / closure evidence:

```text
Pull request:                    #13 — test(conformance): add shared Livewire/HTMX binding-driver matrix
Initial reviewed head:           e71248adb7afbce4c286b4be2504344eb3553da2
CodeRabbit risk:                 LOW
Initial actionable findings:     2 minor documentation consistency findings
Design-state fix:                26324a20a640940d138c5954351133276fb4f4fb
Needs-decision fix:              d8396e36b5e335f89f719f425597cd19ac30a7f3
CodeRabbit threads:              2/2 confirmed addressed and resolved / 0 unresolved
Review-fix validate:             34803374097 — 7/7 green
Decision-promotion head:         bfcbde90ba2892f2e192a1df8d5c7c84310b23c0
Decision-promotion PR validate:  34806672450 — 7/7 green
Decisions:                       D-058 / D-020 — ACCEPTED
Merge commit:                    2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
Post-merge main validate:        34806790431 — 7/7 green
Post-merge browser:              19 files / 319/319 + typecheck
```

**T-604 is closed. M6 is closed.**

## M7 — Conformance / Ecosystem Bridges — IN_PROGRESS

### T-701 — Executable conformance runner — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-14-executable-conformance-runner-design.md
docs/superpowers/plans/2026-09-15-executable-conformance-runner.md
```

Implementation sequence:

```text
Task 1 — pure model/evaluator:                    8351954b95b94902ca91f3d0d8fe78c0d6675a49
Task 2 — subprocess runner/protocol tests:        ecc14a0a9ff571e9c0de20fba7e3fc2f43387a13
Task 3 — Vitest-free shared target support:       706a476b5178e9dcbfb9af942446f810cdcc470c
Task 4 — Livewire process harness:                9563299aec93508b7275a44efe82325f4a69fa26
Task 5 — HTMX process harness:                    7ef086d7547837331ca5662ebcbce49ce7b1f44a
Task 6 — canonical scenarios/manifests:           65e978393b55ffa2f908cb1518f9b768c9e3fbc3
Task 7 — CI + implemented conformance docs:       2c15515a77d2dd1d79ea970a2811ca0c40191ceb
Review-prep tracking:                              041dc9126f63225de0338bdb557446906da78c62
External-review correctness fixes:                 89a968785a09032cbf3b71f7f60c3f14b76c10ab
Concise review handoff:                            b7d2c4aee175da80f0c97d103f915f751c6b0fc7
Review-closure tracking:                           3777fce830ae2d4a1bcc64925af24563f611ea3e
Decision promotion:                                1efe11714e9d3abcd0e8a12fcb90912b23522ad4
Final feature/tracking head:                       5da153c3c0a0f6c38d9b5f01b26899efa24ea949
```

Verified review + decision evidence:

```text
Pull request:                         #14 — feat(conformance): add executable browser conformance runner
External-review RED:                  #794 / 35049387890 — expected failure on new review regressions
Review-fix validate:                  #796 / 35049689721 — SUCCESS
Final reviewed PR-head validate:      #798 / 35049891609 — SUCCESS, 7 jobs total
Review-closure push validate:         #799 / 35050355079 — 7/7 SUCCESS
Decision-promotion PR validate:       #802 / 35058068033 — 7/7 SUCCESS
Tracking-closure push validate:       #803 / 35058313792 — 7/7 SUCCESS
Python runtime:                       CPython 3.12.14
Conformance model + runner tests:     47/47 PASS
Browser runtime:                      20 files / 328/328 Vitest + typecheck
Harness build:                        PASS
Structural validation:                PASS
Canonical runtime matrix:             7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

CodeRabbit external review:

- four actionable threads were opened;
- three valid findings were addressed: closed-v1 scenario/target completeness, repo-root subprocess cwd, and review-handoff concision;
- the HTMX replacement-identity suggestion was not applied because D-053 requires replacement targets to receive a new opaque source identity; CodeRabbit independently rechecked and confirmed that original finding invalid;
- all four threads were individually rechecked and resolved by CodeRabbit: **4/4 resolved / 0 unresolved**;
- no second full CodeRabbit sweep is claimed; closure evidence is thread-level recheck plus fresh CI.

Implemented v1 boundary remains:

- exactly one profile: `runtime-binding/driver`;
- exact target set: `browser/livewire`, `browser/htmx`;
- exact executable runtime scenario set: exact execution, expiry, component stale and no-silent-retarget;
- Livewire applies to all four; HTMX applies to three and receives runner-owned `NOT_APPLICABLE` for component stale;
- Python runner owns canonical selection/applicability/verdicts and enforces the closed v1 target/scenario membership;
- harnesses emit bounded raw observations only;
- one target/scenario pair uses one fresh subprocess with a fixed 10-second timeout;
- manifest commands execute from repository `ROOT`, independent of caller cwd;
- `recommendedCode` / raw `errorCode` remain advisory;
- every claimed profile requires a mandatory positive control;
- `scripts/validate.py` validates structure/configuration and never executes harnesses;
- no semantic production changes exist under `packages/browser-runtime/src/**`;
- no dependency expansion, Laravel production change, T-603 fixture behavior change, standalone-spec extraction, or T-702/T-703/T-704 implementation appeared.

Decision state after promotion:

- `D-059` — **ACCEPTED**;
- `D-060` — **ACCEPTED**;
- `D-061` — **ACCEPTED**;
- `D-026` — **PROPOSED** independently; failure-code recommendations remain advisory.

Merge / post-merge main evidence:

```text
Merge commit:                        50c8c482165a115b8b3b8cb740f123ecf4203041
Post-merge main validate:            #809 / 35080877519 — 7/7 SUCCESS
Post-merge contract validation:      PASS
Post-merge browser runtime:          20 files / 328/328 Vitest + typecheck
Post-merge Python conformance tests: 47/47 PASS on CPython 3.12.14
Post-merge harness build:            PASS
Post-merge canonical matrix:         7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
```

**T-701 is closed.**

### T-702 — Adapter author guide — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

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

Verified implementation gate:

```text
Integration:                    main fast-forward from feat/t-702-adapter-author-guide
Scope/design:                   APPROVED
Implementation:                 DONE / REVIEWED / MERGED / MAIN REVALIDATED
Verified docs/navigation head:  04e69ecb8a4d752732f2ab85eaf0f14798944e68
Implementation validate:        #821 / 35150202622 — 7/7 SUCCESS
Fast-forward main head:             ffd97c9a6608e37a4042d899e079ab751ecc5272
Post-merge main validate:           #825 / 35299100221 — 7/7 SUCCESS
Contract / scripts/validate.py: PASS
PHP matrix:                     4/4 PASS
PHP lint:                       PASS
Browser:                        20 files / 328/328 Vitest + typecheck
Python conformance:             47/47 PASS on CPython 3.12.14
Harness build:                  PASS
Canonical runtime matrix:       7 PASS / 1 NOT_APPLICABLE / 0 FAIL / 0 ERROR
Decision D-062:                 ACCEPTED
Decision D-026:                 PROPOSED
Production changes:             NONE
Canonical spec changes:         NONE
Conformance semantic changes:   NONE
T-703/T-704 work:               NOT STARTED
```

Implemented boundary:

- the Adapter Author Guide is explicitly subordinate to canonical contracts, accepted decisions, and executable conformance semantics; it does not create a second specification;
- `docs/adapters/README.md` provides the author entry point, role picker, canonical reference map, and non-normative authority warning;
- `docs/adapters/author-guide.md` documents definition/import, runtime/binding, and surface/projection roles, the core/runner responsibility matrix, the 12-step author workflow, exact-target/runtime invariants, and portable-invariant vs Livewire/HTMX reference techniques;
- `docs/adapters/security.md` documents explicit exposure, discovery-vs-invocation, caller-input vs trusted-context authority, exact-target/no-retarget, fail-closed behavior, bounded cancellation, and review checks;
- `docs/adapters/conformance.md` carries D-059 through D-061 forward without widening them: truthful profile/capability claims, runner-owned selection/applicability/verdicts, bounded raw harness observations, repo-local process semantics, revision-bounded evidence, and bounded compatibility wording;
- D-026 remains PROPOSED and no new global error enum is introduced;
- D-062 is ACCEPTED only as the reviewed guide-authority boundary; it does not create or authorize new adapter semantics;
- T-703 Laravel MCP projection and T-704 OpenAPI import remain outside T-702 and are not started;
- `main..feat/t-702-adapter-author-guide` contains no semantic changes under `packages/browser-runtime/src/**`, `packages/laravel/src/**`, `spec/0.1/**`, `conformance/targets/**`, `scripts/conformance_model.py`, or `scripts/run_conformance.py`.

External review closure:

```text
Review-only PR:                 #15 — CLOSED WITHOUT MERGE
Review range:                    7d96160286a7a8a618635fa54f1bf40a3bcc2baa..618f4698f775ea5057caed0e3c7e4bcbf47f83b7
CodeRabbit actionable findings:  1 Minor
Review fix:                      d5c67a5ee403cdd6cc5805ac072dc05f6139d309
Review-fix validate:             #830 / 35299933662 — 7/7 SUCCESS
CodeRabbit threads:              1/1 confirmed addressed / 0 unresolved
Reviewed fix on main:            d5c67a5ee403cdd6cc5805ac072dc05f6139d309
Post-review main validate:       #832 / 35300067389 — 7/7 SUCCESS
```

The only actionable review finding was a documentation-plan preflight gap: `git status --short` printed dirty state without enforcing it. The plan now uses `test -z "$(git status --short)"`, matching the documented clean-working-tree gate. No adapter/runtime/spec/conformance semantics changed.

Decision promotion / closure evidence:

```text
Decision:                        D-062 — ACCEPTED
Decision-promotion head:         f37955979bf0c45878b449b5dd7c608046d94fb4
Decision-promotion validate:     #834 / 35302680228 — 7/7 SUCCESS
Contract / PHP / browser matrix: PASS
```

**T-702 is closed.** T-703/T-704 do not begin automatically.

### T-703 — Laravel MCP projection using a maintained MCP implementation — IN_PROGRESS / DESIGN APPROVED / PLAN APPROVED / IMPLEMENTATION NOT STARTED

Design:

```text
docs/superpowers/specs/2026-09-18-laravel-mcp-projection-design.md
```

Approved scope/design boundary:

- integration uses maintained official `laravel/mcp`; SurfaceRelay does not implement MCP protocol/transports;
- MCP integration is isolated in optional `packages/laravel-mcp`; `packages/laravel` remains MCP-independent;
- v1 projects MCP Tools only;
- exposure is an explicit exact-`id + version` MCP allow-list; `ActionRegistry::all()` is not exposure authority;
- only `portable` and `headless` actions are eligible; `page_scoped` and `browser_local` fail closed;
- MCP arguments remain untrusted Action input;
- actor/tenant authority remains existing trusted Laravel runtime state;
- confirmation receipt and idempotency key may enter only as namespaced non-authoritative metadata candidates and remain subject to existing server verification;
- invocation converges on the existing ActionBus / ActionResultNormalizer path;
- T-701 conformance scope is not broadened;
- MCP Resources, Prompts, Apps/client support and T-704 are out of scope.

Decision state:

- D-063 — PROPOSED;
- D-064 — PROPOSED;
- D-026 remains PROPOSED independently.

Implementation plan: `docs/superpowers/plans/2026-09-18-laravel-mcp-projection.md` — **APPROVED**.  
Implementation: **NOT STARTED**.

Design-head validation: `#837 / 35319627802` — **7/7 SUCCESS**. The next gate is explicit T-703 implementation-start approval only. No production code or dependency change begins automatically.

- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

M6 remains **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-701 and T-702 are **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-703 is **IN_PROGRESS / DESIGN APPROVED / PLAN APPROVED / IMPLEMENTATION NOT STARTED**. `D-059`, `D-060`, `D-061`, and `D-062` are **ACCEPTED**; `D-026`, `D-063`, and `D-064` are **PROPOSED**. M7 remains **IN PROGRESS**. T-704 remains TODO. No implementation or subsequent task begins automatically.