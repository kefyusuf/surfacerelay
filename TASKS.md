# Implementation Task Board

Status values: `TODO`, `IN_PROGRESS`, `BLOCKED`, `DONE`.

A task is DONE only after required verification passes. SurfaceRelay implements one task at a time; do not begin the next task automatically.

> Historical per-task evidence through T-505 is preserved in `docs/archive/TASKS-through-T505-preclosure.md`. Design specs, implementation plans, `STATUS.md`, `REVIEW_REQUEST.md`, PR discussions, and Git history contain the detailed evidence for later tasks.

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

### T-601 — Explicit HTMX binding descriptor — DONE / EXTERNAL REVIEW HARDENING

**Outcome:** browser-runtime contains a pure driver-owned HTMX RuntimeBinding descriptor proving that the existing generic RuntimeBinding envelope can carry a materially different exact target without introducing HTMX execution, Laravel coupling, or a frozen wire-contract change.

Accepted descriptor boundaries:

- `driver=htmx`, `lifecycle=page`;
- exact target keys: `sourceId`, `method`, `path`, `inputNames`, `requiredInputNames`;
- exact five-method subset and bounded same-origin absolute-path-reference grammar;
- finite named input mapping derived only from a closed top-level ActionDefinition object schema;
- open/reference/composed/conditional mapping forms fail closed;
- `dependentRequired` and legacy `dependencies` are explicitly rejected after external-review hardening;
- required names are a unique non-empty subset of exact property names;
- nested input values are not flattened;
- arbitrary RuntimeBinding JSON is independently consumer-validated;
- output target/list snapshots are defensive and frozen;
- no actor, tenant, role, record, selection, browser-session, confirmation, idempotency, or authorization authority enters the target;
- no DOM/HTMX/network/cancellation behavior exists in T-601;
- no HTMX dependency, Laravel production source, or `spec/0.1/**` change;
- D-053 is **ACCEPTED for descriptor semantics only**;
- D-020 remains **PROPOSED** through T-604.

Design / plan:

```text
docs/superpowers/specs/2026-09-11-htmx-binding-descriptor-design.md
docs/superpowers/plans/2026-09-11-htmx-binding-descriptor.md
```

Implementation verification before external review:

```text
Design checkpoint:            cca98af90323819789a56d28a2174b9b7ea25059 / 34625130732 — 7/7 green
Implementation plan:          1e47c6bb4121bcc1fc43e69c73c213d17092b5c1 / 34629136719 — 7/7 green
Implementation/scope head:    b06204e2c7acf6be01df3fec5085e55ddd650329 / 34654979136 — 7/7 green
Review-prep head:             c4e9b841d98464cc2fdb0b3fb259e9cebba08c76 / 34655391095 — 7/7 green
Initial PR #10 CI:            34655515805 — 7/7 green
Initial browser:              172/172; HTMX focused 69/69; typecheck green
```

External review:

```text
PR:                           #10
CodeRabbit run:               77dbb63c-4223-4177-a56a-8cad74daddb8
Initial findings:             1 Major + 3 Minor
Major:                        conditional required-key schemas omitted from descriptor mapping
```

Major finding hardening:

```text
RED:                          9b241e4ef58740e512d57fde16efd1caaf8db5bd / 34656195469
RED result:                   174 total — 172 passed / exactly 2 failed
                              dependentRequired + dependencies
GREEN:                        0fd29621c1ae3084649095f8a8595e741c504e50 / 34656249493 — 7/7 green
Browser after GREEN:          174/174; HTMX focused 71/71; typecheck green
Contract / PHP / lint:        green
```

Review hygiene hardening:

- milestone status token normalized to `IN_PROGRESS`;
- `REVIEW_REQUEST.md` reduced to a concise handoff;
- original design spec is retained as the pre-implementation design snapshot; the implementation plan records the later repository-specific refinement that avoided creating a new `src/index.ts` barrel solely for T-601.

**Current gate:** resolve/re-review PR #10. Do not merge or start T-602 yet.

- T-602 — HTMX browser driver — TODO / NOT STARTED.
- T-603 — Non-Laravel HTMX fixture app — TODO.
- T-604 — Shared conformance against Livewire + HTMX — TODO.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-601 implementation is complete and is in PR #10 external-review hardening. **Do not merge and do not begin T-602 until the review gate is explicitly advanced.**
