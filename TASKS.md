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

### T-603 — Non-Laravel HTMX fixture app — IMPLEMENTED / EXTERNAL REVIEW IN PROGRESS

Design / plan:

```text
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
```

Verified proof boundary:

- private Node 22 fixture under `examples/htmx-prep-list/`;
- real `htmx.org@2.0.10` + Playwright Chromium;
- actual browser-runtime TS compiled to fixture `.tmp/runtime/` ESM;
- existing `prep_list.add_item@1` ActionDefinition reused;
- exactly one business mutation route: `POST /items`;
- human click and production `HtmxBrowserDriver.execute()` converge on the same real HTMX path and DOM swap;
- explicit Action input overrides stale same-name form state while ordinary host state is preserved;
- page reload renews sourceId + bindingId;
- equivalent DOM replacement cannot inherit old binding; stale execution dispatches zero `/items` requests;
- body bounds, duplicate/missing/invalid input, media-type validation, method checks, traversal safety, and rendering escaping are executable proofs;
- fixture CI uses read-only contents permission and `persist-credentials: false`;
- production browser-runtime, Laravel, frozen spec, existing ActionDefinition, and `validate.yml` remain unchanged.

Original implementation evidence:

```text
Verified fixture head:        31c0b85af56aaa06cac4efc2bced36bee0befcc2
validate:                     34719068926 — 7/7 green
htmx-fixture:                 34719068934 — 1/1 green / 8/8 Playwright
browser-runtime:              17 files / 297/297 + typecheck
Review-prep head:             0348bb6b50a6a8f36c4b3f1315515dd6e9213c87
Review-prep validate:         34722974304 — 7/7 green
Review-prep diff:             tracking-only
```

External review — PR #12:

```text
CodeRabbit review:            4 actionable + 1 nitpick initially
Media-type RED:               da528e4db76af7c04a351c99a20df2e2da7387dd
Media-type RED run:           34725182292 — 8 total / 7 passed / exactly 1 failed
Failure:                      expected 415, received 201
Media-type GREEN:             226f8169ee7cf251a079cd9f763e21791d49b39a
Workflow security hardening:  7284dd21292c17fc35ddfd3bfb045d50e3dab38c
Review-fix validate:          34725480849 — 7/7 green
Review-fix fixture:           34725480861 — 1/1 green / 8/8 Playwright
```

Review findings addressed:

- near-miss `application/x-www-form-urlencoded-invalid` now fails `415`;
- fixture workflow token is restricted to `contents: read`;
- checkout credentials are not persisted into npm-controlled steps;
- the earlier review-prep readiness proof is now explicitly recorded;
- `REVIEW_REQUEST.md` is reduced to a concise handoff; historical evidence remains in `STATUS.md` / this task board.

Decision state:

- `D-057` — **ACCEPTED** for the verified real non-Laravel HTMX fixture proof.
- `D-020` — **PROPOSED**; shared portability remains gated on T-604.

Current gate:

**Finish PR #12 review-thread closure and final docs-only exact-head validation. Merge remains pending explicit authorization. Do not begin T-604 automatically.**

### T-604 — Shared conformance against Livewire + HTMX — TODO / NOT STARTED

`D-020` remains proposed until this task proves the shared scenarios and an explicit decision gate promotes or rejects the portability claim.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-603 implementation is complete and under external review in PR #12. `D-057` is accepted for the verified fixture boundary, while `D-020` remains proposed and T-604 remains not started.
