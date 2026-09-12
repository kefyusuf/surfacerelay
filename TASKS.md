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

### T-602 — HTMX browser driver — IMPLEMENTED / EXTERNALLY REVIEWED / FINDINGS RESOLVED / MERGE PENDING

**Implementation state:** complete and reviewed on `feat/htmx-browser-driver`; PR **#11** is open. T-603 is **NOT STARTED**.

Design / plan:

```text
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
docs/superpowers/plans/2026-09-12-htmx-browser-driver.md
```

Accepted implementation boundaries:

- host page HTMX runtime only; reference compatibility is HTMX 2.x;
- no `htmx.org`, DOM emulator, browser automation, or other dependency added;
- exact one-source resolution by `data-surfacerelay-htmx-source`; no replacement/similar retargeting;
- exactly one physical five-verb `hx-*` / `data-hx-*` request declaration;
- exact raw method/path equality and same-origin pre-dispatch defense;
- later host HTMX hooks such as `htmx:configRequest` remain host behavior and are not SurfaceRelay authorization/binding authority;
- deterministic allowlisted own Action-input mapping; arrays/plain objects remain one top-level JSON-string value;
- lossy/non-JSON/accessor/sparse/cyclic/custom values fail closed;
- `__proto__` is preserved as an exact own mapped data key; `hasOwnProperty` is rejected because it conflicts with HTMX 2.x object-values processing;
- inherited object/array `toJSON` hooks cannot replace validated content because structured encoding walks the validated own data-property graph directly;
- ordinary host form/request state remains untrusted host state;
- reference sources fail closed for `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, and active source validation;
- busy exact source returns `htmx_source_busy`; driver never manufactures queue/replace/broad abort behavior;
- strong no-dispatch cancellation only before `htmx.ajax()` invocation; post-frontier caller abort does not replace the natural HTMX success/failure result;
- successful execution preserves `Promise<void>` semantics; underlying HTMX rejection identity is preserved;
- strict RuntimeBinding expiry parsing/classification is shared with Livewire while preserving Livewire's separate non-string vs invalid-string error messages;
- existing `BindingDriver`, `RuntimeBinding`, `DriverRegistry`, and WebMCP lifecycle contracts remain unchanged;
- no Laravel production or `spec/0.1/**` change;
- no T-603 fixture or T-604 shared-conformance claim.

Implementation/test surface:

```text
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/htmx-browser-driver.ts
packages/browser-runtime/tests/runtime-binding-expiry.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
```

Implementation and self-review evidence:

```text
Design checkpoint:                 42cca940af35b3dff918a641b619d578f839047d / 34682081839 — 7/7 green
Plan gate:                         11f5a23cfd28f2b26feb13d880f2eb123b43154d / 34684333012 — 7/7 green
Expiry RED:                        acdfdd7486674b4374d02ef59837ca91ba5e7f7e
Expiry GREEN/refactor:             14c2e136ae3f8a21557132ee502a7e036892c540 + 0a13befca39a5c58cb29212b0574e68555e141f4
Runtime RED:                       a97323b16d3bca58147a8162dbeb2be41d9f5b05
Runtime GREEN/typecheck:           d7873bb64d6d22ab81593914676148638c7944cc + 3a0e1253de50d032f462fff0ed7d256a18548edd + e15fa915a31e61a461e3da6150d7b01baba35260
Input mapping RED/GREEN:           dad9187d898bf3ce2e5e0a42d181cfb2b0d22c1c / f844f57e1b0a39eb1eda7652c4c3b8b28991d9aa
Driver-core RED/GREEN:             7310fa5fb3af6009f7106b899812f25df3abb16d / 612c3493ba493e1b4b760dd55adc5f1cb73da0dc
Source-policy RED/GREEN:           99b6247746254d1c4e86e5b73c6435914eb847bd / 27086b6094d3529bcc2e179742adeb7f9e55eccb
Cancellation characterization:     54548b30c0ab1bb398c89cc3e343c887ac908461
WebMCP integration:                c887d64cec7f4f9c5c0475a44e5d3c8a3a0c4f3c
Special-key RED:                   a06168befbc10010f8164c0515af0892ec154372 — exactly 2 failures
Special-key GREEN:                 0d2021674e43c0ec4bf0a3e365e0915221b51e5e / 34695125241 — 7/7 green
```

External review / hardening:

```text
Pull request:                       #11 — feat(htmx): add exact-source browser driver
CodeRabbit review:                  2 actionable inline findings (both Minor)
Finding 1 RED:                      adde02f3c457169d9d2c47418b53d2d4413410be — 295 passed / exactly 2 failed
Finding 1 GREEN:                    2ec197213e966c96264b429be3e316c91c69c19e
Finding 2:                          plan/Livewire error-message mismatch — docs-only correction
Review-aligned head:                a5f1bd8e5bd64548d78b4a37411314af664e5eb3
Review-aligned CI:                  34699961997 — 7/7 green
Browser typecheck:                  green
Browser Vitest:                     17 files / 297/297 tests
HTMX descriptor:                    71/71
HTMX browser driver:                49/49
HTMX input mapping:                 28/28
HTMX runtime:                       20/20
HTMX cancellation:                  5/5
HTMX WebMCP integration:            2/2
Shared expiry:                      19/19
Livewire driver regression:         33/33
Livewire cancellation regression:   11/11
Livewire WebMCP regression:         2/2
Contract validator:                 green
PHP lint:                           green
PHP matrix:                         4/4 green
Review threads:                     2/2 resolved / 0 unresolved
```

CodeRabbit explicitly confirmed both fixes in-thread. A second complete CodeRabbit review pass was unavailable within the included hourly quota; the two findings from the completed review were individually rechecked and confirmed. Its generic docstring-coverage warning is not a SurfaceRelay CI/contract gate and did not trigger bulk JSDoc churn.

Decision state after reviewed implementation:

- `D-054` — **ACCEPTED** for the exact-source host HTMX 2.x execution boundary.
- `D-055` — **ACCEPTED** for busy-source failure and pre-ajax-only strong cancellation semantics.
- `D-056` — **ACCEPTED** for deterministic Action-input integrity/reference-source exclusions.
- `D-020` — **PROPOSED**; portability remains gated on T-603/T-604.

Scope verification against `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4` confirms no changes to:

```text
packages/laravel/src/**
spec/0.1/**
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
examples/htmx/**
```

**Current gate:** PR #11 merge authorization. Do not merge, begin T-603, run T-604 shared conformance, or promote D-020 without the next explicit gate.

- T-603 — Non-Laravel HTMX fixture app — TODO / NOT STARTED.
- T-604 — Shared conformance against Livewire + HTMX — TODO.

## M7 — Conformance / Ecosystem Bridges — TODO

- T-701 — Executable conformance runner — TODO.
- T-702 — Adapter author guide — TODO.
- T-703 — Laravel MCP projection using a maintained MCP implementation — TODO.
- T-704 — Optional OpenAPI importer as a secondary adapter — TODO.

## Current boundary

T-602 is **IMPLEMENTED / EXTERNALLY REVIEWED / FINDINGS RESOLVED / MERGE PENDING** on PR #11. D-054/D-055/D-056 are accepted for the verified reference-driver behavior only. D-020 remains proposed. **T-603 must not start automatically.**