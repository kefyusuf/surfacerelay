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

## M6 — HTMX Portability Proof — IN_PROGRESS

### T-601 — Explicit HTMX binding descriptor — DONE / REVIEWED / MERGED / MAIN REVALIDATED

```text
Decision:                    D-053 — ACCEPTED
Final feature head:          e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Merge commit:                96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:          34657967629 — 7/7 green
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

**T-602 is closed.**

### T-603 — Non-Laravel HTMX fixture app — DONE / REVIEWED / MERGED / MAIN REVALIDATED

Design / plan:

```text
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
```

Verified proof boundary:

- private Node 22 fixture under `examples/htmx-prep-list/`;
- real `htmx.org@2.0.10` + Playwright Chromium;
- actual browser-runtime TypeScript compiled to fixture `.tmp/runtime/` ESM;
- existing `prep_list.add_item@1` ActionDefinition reused;
- exactly one business mutation route: `POST /items`;
- human click and production `HtmxBrowserDriver.execute()` converge on the same real HTMX path and DOM swap;
- explicit Action input overrides stale same-name form state while ordinary host state remains present;
- page reload renews sourceId + bindingId;
- equivalent DOM replacement cannot inherit old binding; stale execution dispatches zero `/items` requests;
- body bounds, duplicate/missing/invalid input, exact media-type validation, method checks, traversal safety, and rendering escaping are executable proofs;
- fixture CI uses `permissions: contents: read` and `persist-credentials: false`;
- production browser-runtime, Laravel, frozen spec, existing ActionDefinition, and `validate.yml` remain unchanged.

External review / closure evidence:

```text
Pull request:                    #12 — test(htmx): add real non-Laravel fixture proof
Decision:                        D-057 — ACCEPTED
Original verified fixture head:  31c0b85af56aaa06cac4efc2bced36bee0befcc2
Original validate:               34719068926 — 7/7 green
Original fixture:                34719068934 — 8/8 Playwright

CodeRabbit review:               4 actionable + 1 nitpick initially
Media-type RED:                  da528e4db76af7c04a351c99a20df2e2da7387dd
Media-type RED run:              34725182292 — 8 total / 7 passed / exactly 1 failed
Failure:                         expected 415, received 201
Media-type GREEN:                226f8169ee7cf251a079cd9f763e21791d49b39a
Workflow security hardening:     7284dd21292c17fc35ddfd3bfb045d50e3dab38c

Final feature/review head:        076108554d6995fea65108ca07c9b64d3994d459
Final feature validate:           34727726895 — 7/7 green
Final feature fixture:            34727726888 — 8/8 Playwright
Final feature browser:            17 files / 297/297 + typecheck
CodeRabbit threads:               4/4 actionable resolved / 0 unresolved

Merge commit:                     e98c919b90f9f19b58ae56b88e391f1abbb179e7
Post-merge main validate:         34758253025 — 7/7 green
Post-merge main fixture:          34758253003 — 8/8 Playwright
Post-merge browser:               17 files / 297/297 + typecheck
```

Review closure:

- workflow token restricted to `contents: read`;
- checkout credentials are not persisted into npm-controlled steps;
- near-miss form media types fail `415` and are covered by a RED/GREEN regression;
- readiness and closure-head evidence was independently rechecked by CodeRabbit;
- `REVIEW_REQUEST.md` is a concise handoff/closure record;
- all four actionable CodeRabbit threads were explicitly confirmed and resolved.

Decision state:

- `D-057` — **ACCEPTED** for the real non-Laravel HTMX fixture proof.
- `D-020` — **PROPOSED**; shared portability remains gated on T-604.

**T-603 is closed.**

### T-604 — Shared conformance against Livewire + HTMX — DESIGN APPROVED / IMPLEMENTATION PLAN WRITTEN / IMPLEMENTATION NOT STARTED

Design / plan:

```text
docs/superpowers/specs/2026-09-13-binding-driver-conformance-design.md
docs/superpowers/plans/2026-09-14-binding-driver-conformance.md
```

Approved design boundary:

- one shared executable binding-driver conformance matrix will run against the production `LivewireBrowserDriver` and `HtmxBrowserDriver`;
- shared assertions cover genuinely common observable semantics only: fail-closed target validation, expiry classification, Action-input mappability, exact-target stale/no-retarget behavior, and cancellation before framework dispatch;
- fail-closed cases must prove zero unintended framework dispatch;
- Livewire and HTMX retain their different target shapes, lifecycle values, runtime APIs, framework-specific errors, cancellation mechanisms, and successful return-value behavior;
- the shared matrix uses test-only thin adapters/fake runtimes and does not introduce a new production driver abstraction;
- existing driver-specific and integration suites remain authoritative for framework-local behavior;
- T-604 does not implement the repository-wide T-701 executable conformance runner and does not promote runtime fixture-manifest scenarios wholesale.

Implementation-plan boundary:

- create one shared 11-case Vitest suite under `packages/browser-runtime/tests/support/`;
- run that exact suite through one Livewire adapter and one HTMX adapter using the production drivers;
- add one `.typecheck.ts` sentinel so both test adapters are included by the existing TypeScript configuration;
- expected new shared proof: 2 test files / 22 tests, taking the current full browser baseline from 17 files / 297 tests to 19 files / 319 tests;
- no production `packages/browser-runtime/src/**`, frozen spec, Laravel, fixture, workflow, package dependency, or `tsconfig.json` change is planned;
- explicit verification includes the original driver-specific suites, full browser suite, TypeScript typecheck, contract validation, branch-head 7/7 `validate`, and a fresh T-603 8/8 Chromium fixture run;
- execution stops at external-review preparation; decision promotion and M6 closure remain separate explicit gates.

Decision state:

- `D-058` — **PROPOSED** for the shared behavioral binding-driver conformance boundary.
- `D-020` — **PROPOSED** until the complete T-604 implementation, verification, external review, and explicit decision-promotion gate succeeds.

Implementation has **not** started. The implementation plan is written and ready for review, but code/test execution requires a separate explicit authorization.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-603 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-604 design and implementation plan are recorded on `feat/binding-driver-conformance`; `D-058` and `D-020` remain **PROPOSED**. T-604 implementation is **NOT STARTED** and requires a separate explicit next step.
