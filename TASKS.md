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
- Reviewed checkpoint: `068347ac6d1bba645ab1c311daf918f87298b2e8`.

## M3 — Browser Runtime / WebMCP — DONE / REVIEWED

- T-301 — DriverRegistry — DONE / REVIEWED.
- T-302 — WebMCP semantic projection — DONE / REVIEWED.
- T-303 — Async registration lifecycle — DONE / REVIEWED / MERGED.
- T-304 — Livewire browser driver — DONE / REVIEWED / MERGED.
- T-305 — Cancellation propagation — DONE / REVIEWED / MERGED.

## M4 — Production Trust Controls — DONE / REVIEWED / MERGED

- T-401 — Confirmation challenge/receipt — DONE / REVIEWED.
- T-402 — Idempotency store — DONE / REVIEWED.
- T-403 — Output policy/redaction — DONE / REVIEWED.
- T-404 — Structured audit events — DONE / REVIEWED.

## M5 — Filament Vertical — DONE / REVIEWED / MERGED / MAIN REVALIDATED

- T-501 — Record context binding — DONE / REVIEWED.
- T-502 — Current-selection trusted context — DONE / REVIEWED.
- T-503 — Active-filter context — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-504 — Confirmation bridge — DONE / REVIEWED / MERGED / MAIN REVALIDATED.
- T-505 — Multi-tenant order operations demo — DONE / REVIEWED / MERGED / MAIN REVALIDATED.

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

Outcome: browser-runtime contains a pure driver-owned HTMX RuntimeBinding descriptor with exact target keys `sourceId`, `method`, `path`, `inputNames`, and `requiredInputNames`. It proves the generic RuntimeBinding envelope can carry a materially different target without introducing HTMX execution, Laravel coupling, or frozen wire-contract changes.

Final closure:

```text
Design:                      docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
Plan:                        docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
Decision:                    D-053 — ACCEPTED for descriptor semantics only
Final feature head:          e4cbf0b3831863f4e0a7c89a0700726246b6f4b9
Merge commit:                96581ff9d12dba5487b4831c3bc146081945decb
Post-merge main CI:          34657967629 — 7/7 green
Browser on main:             TypeScript typecheck + 174/174 Vitest
Focused HTMX descriptor:     71/71
PHP baseline:                595 tests / 3164 assertions
Contract / PHP lint:         green
```

`D-020` remained PROPOSED. T-601 introduced no DOM/HTMX/network/cancellation behavior.

### T-602 — HTMX browser driver — DONE / REVIEWED / MERGED / MAIN REVALIDATED

**Implementation state:** complete, externally reviewed, merged through PR **#11**, and revalidated on `main`.

Design / plan:

```text
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
docs/superpowers/plans/2026-09-12-htmx-browser-driver.md
```

Accepted implementation boundaries:

- host page HTMX runtime only; reference compatibility is HTMX 2.x;
- exact one-source resolution by `data-surfacerelay-htmx-source`; no replacement/similar retargeting;
- exactly one physical five-verb `hx-*` / `data-hx-*` request declaration;
- exact raw method/path equality and same-origin pre-dispatch defense;
- deterministic allowlisted own Action-input mapping and JSON-safe structured encoding;
- conservative fail-closed source policy for HTMX behaviors that can ambiguously alter Action values or dispatch semantics;
- busy exact source fails closed; no manufactured queue/replace/broad-abort behavior;
- strong no-dispatch cancellation only before `htmx.ajax()` invocation;
- strict RuntimeBinding expiry parsing/classification is shared with Livewire without changing Livewire behavior;
- generic browser contracts, Laravel production source, `spec/0.1/**`, and library dependency manifests remain unchanged.

External review / closure:

```text
Pull request:                      #11 — feat(htmx): add exact-source browser driver
CodeRabbit:                        2 actionable findings / 2 resolved / 0 unresolved
External-review RED:               adde02f3c457169d9d2c47418b53d2d4413410be — 295 passed / exactly 2 failed
External-review GREEN:             2ec197213e966c96264b429be3e316c91c69c19e
Final feature head:                ebad0f04a3b540a5a1536350038d0919ff600ebd
Final feature CI:                  34700403577 — 7/7 green
Merge commit:                      ee9af986f22cba45b05c59059c11e68ac46111fd
Post-merge main CI:                34701911188 — 7/7 green
Final closure main:                83df122d84f6881a4a3541bd5c72e1108e5d1e48
Final closure CI:                  34702679885 — 7/7 green
Browser on final closure main:     17 files / 297/297 tests + typecheck
Contract / PHP lint / PHP matrix:  green
```

Decision state after closure:

- `D-054` — **ACCEPTED** for the exact-source host HTMX 2.x execution boundary.
- `D-055` — **ACCEPTED** for busy-source failure and pre-ajax-only strong cancellation semantics.
- `D-056` — **ACCEPTED** for deterministic Action-input integrity/reference-source exclusions.
- `D-020` — **PROPOSED**; portability remains gated on T-603/T-604.

**T-602 is closed.**

### T-603 — Non-Laravel HTMX fixture app — IN_PROGRESS / DESIGN SPEC UNDER REVIEW

**Implementation state:** **NOT STARTED.**

Design gate approved in chat on 2026-09-12. Written spec:

```text
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
```

Selected proof boundary:

- independent private fixture package under `examples/htmx-prep-list/`;
- plain Node 22 built-in HTTP server bound only to `127.0.0.1:4173`;
- real pinned `htmx.org@2.0.10` browser runtime;
- real Playwright/Chromium browser execution;
- actual merged SurfaceRelay browser-runtime TypeScript compiled to temporary ESM for the fixture; no copied/reimplemented driver;
- existing `examples/prep-list/action.add-item.json` (`prep_list.add_item@1`) reused rather than duplicated;
- exactly one business mutation route: `POST /items`;
- normal human HTMX click and SurfaceRelay driver invocation both converge on `/items`, the same server state mutation, and source-defined `hx-target` / `hx-swap` behavior;
- ordinary hidden/form host state is preserved, while explicit SurfaceRelay Action input overrides a same-named stale form value in the real HTMX request;
- every full page render issues a fresh page-scoped `sourceId` and `bindingId`;
- a real DOM replacement with equivalent HTMX method/path behavior must not let the old binding retarget; stale execution sends no `/items` mutation request;
- in-memory server state is proved through full-page reload persistence;
- only `POST /__test/reset` exists as test-only server state control; it is not a business mutation route;
- client test bridge may only delegate to production `HtmxBrowserDriver.execute()` or perform DOM-only source replacement; no direct `fetch`, XHR, `htmx.ajax`, or business mutation;
- explicit static routes and flat `/runtime/*.js` filename rules prevent project-root/path-traversal serving;
- rendered item values and embedded binding JSON are escaped for their HTML/script contexts;
- fixture dependencies are isolated to the fixture package; browser-runtime and Laravel dependency manifests/locks remain unchanged;
- real-browser CI lives in a separate path-filtered `.github/workflows/htmx-fixture.yml` job so unrelated docs/PHP changes do not install Chromium;
- existing `validate` workflow remains semantically unchanged and must stay 7/7 green;
- T-603 does not implement Node versions of Laravel trust controls and does not claim T-604 shared conformance.

Proposed decision:

- `D-057` — real non-Laravel HTMX fixture proof boundary — **PROPOSED**.
- `D-020` remains **PROPOSED** and gated on T-604.

Expected implementation surface after written-spec + implementation-plan approval:

```text
examples/htmx-prep-list/package.json
examples/htmx-prep-list/package-lock.json
examples/htmx-prep-list/tsconfig.runtime.json
examples/htmx-prep-list/server.mjs
examples/htmx-prep-list/client.mjs
examples/htmx-prep-list/playwright.config.mjs
examples/htmx-prep-list/README.md
examples/htmx-prep-list/tests/prep-list.spec.mjs
.github/workflows/htmx-fixture.yml
```

Required real-browser proof matrix includes:

```text
runtime/bootstrap identity
normal human HTMX path
agent path + same-name form-value override
human/agent normalized request convergence
page-lifecycle source renewal
real-DOM stale replacement with zero mutation request
```

Explicitly out of scope during T-603:

```text
packages/browser-runtime/src/** production behavior changes
packages/laravel/** production behavior changes
spec/0.1/**
HTMX 4/beta compatibility
Node ActionBus/authorization/confirmation/idempotency/audit/output-policy implementation
additional business mutation endpoints
database/persistent datastore
T-604 shared conformance
D-020 promotion
```

**Current T-603 gate:** written design spec review. Do not write the implementation plan, fixture files, package manifests, or CI workflow until the user approves the committed written spec.

- T-604 — Shared conformance against Livewire + HTMX — TODO / NOT STARTED.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-602 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**. T-603 is active only at its **written design-spec review gate** on `feat/htmx-fixture`; implementation and implementation planning are **NOT STARTED**. `D-057` and `D-020` remain proposed. Do not begin T-604 automatically.