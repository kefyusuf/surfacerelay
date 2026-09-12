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

### T-603 — Non-Laravel HTMX fixture app — IMPLEMENTED / SELF-REVIEWED / READY FOR EXTERNAL REVIEW

Design / plan:

```text
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
```

Verified proof boundary:

- independent private fixture package at `examples/htmx-prep-list/`;
- plain Node 22 built-in HTTP server bound to `127.0.0.1:4173`;
- real pinned `htmx.org@2.0.10` + Playwright Chromium;
- actual browser-runtime TypeScript compiled into ignored fixture `.tmp/runtime/` ESM;
- exact existing `prep_list.add_item@1` ActionDefinition reused;
- one business mutation route: `POST /items`;
- human click and production `HtmxBrowserDriver.execute()` converge on the same real HTMX request path, server mutation, fragment, target, and swap;
- explicit agent Action input overrides stale same-name form state while ordinary hidden host state remains present;
- full reload proves server persistence and renews page-scoped `sourceId` + `bindingId`;
- equivalent real-DOM replacement cannot inherit the old binding; stale execution sends zero `/items` requests;
- reset endpoint is test-only and cannot add business data;
- bounded request body, duplicate/missing/invalid name rejection, method checks, traversal-safe static serving, and HTML/script escaping are covered;
- fixture-only dependencies do not change browser-runtime/Laravel manifests;
- `.github/workflows/validate.yml`, production browser-runtime, Laravel source, `spec/0.1/**`, and the existing ActionDefinition remain unchanged.

TDD evidence:

```text
Bootstrap RED:               a61e2899532706059fa76ee38ea781732257da9d / 34710117812
Bootstrap GREEN:             a436bd2a9a4032877b45218a6cd6a35e801a1d07 / 34710621196
Human-path RED:              6c11ada77b26fcfe0eddd5940e63f3cecf2af4f2 / 34710804593
Human-path GREEN:            3a7e6de8777dad5b3889d390df55259bef2e60f6 / 34710965945
Driver-path RED:             ae2dcd4dab234ef005dd94875a2cc1476e127bab / 34711093202 — 2 passed / exactly 1 failed
Driver-path GREEN:           e154743cfc8509ec22889cff49581c85ebbbb632 / 34717781774
Convergence/lifecycle:       28f79fc9dcd172254d4d3ecd3d16e1aa3c4e1d81 / 34717961616 — 5/5 green
Hardening RED:               d0733055514ddef42907bbcd26018aa3bfef1f63 / 34718189031 — 6 passed / exactly 2 failed
Hardening GREEN:             06a261636cd45fac2d95fc54228a2902f25c0bf1 + 71262bee0f5f011a87d2f1fb3216ebc528e9241e
```

Verified fixture implementation head:

```text
31c0b85af56aaa06cac4efc2bced36bee0befcc2
```

Verification on that exact head:

```text
validate workflow:       34719068926 — 7/7 green
htmx-fixture workflow:   34719068934 — 1/1 green
Playwright:              8/8 real Chromium tests
browser-runtime:         17 files / 297/297 Vitest + TypeScript typecheck
contract/php lint/matrix: green
```

Decision state:

- `D-057` — **ACCEPTED** for the verified real non-Laravel HTMX fixture proof.
- `D-020` — **PROPOSED**; shared portability remains gated on T-604.

Current gate:

**External review / PR preparation.** Do not begin T-604 automatically.

### T-604 — Shared conformance against Livewire + HTMX — TODO / NOT STARTED

`D-020` remains proposed until this task proves the shared scenarios and an explicit decision gate promotes or rejects the portability claim.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-603 implementation and self-review are complete on `feat/htmx-fixture`; external review/PR/merge have not yet occurred. `D-057` is accepted for the verified fixture boundary, while `D-020` remains proposed and T-604 remains not started.