# External Review Request — T-604 Shared Binding-Driver Conformance

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/binding-driver-conformance`
- **Task:** `T-604 — Shared conformance against Livewire + HTMX`
- **State:** **IMPLEMENTED / VERIFIED / READY FOR EXTERNAL REVIEW**
- **Implementation head:** `9d49c22ac2127c7ade6e235fae488f87490feb43`
- **Implementation validate:** `34793229849` — **7/7 jobs green**
- **Browser:** **19 files / 319/319 Vitest + TypeScript typecheck**
- **Shared matrix:** **22/22** — 11 Livewire + 11 HTMX
- **Fresh real HTMX fixture rerun:** run `34758253003`, job `103821380728` — **8/8 Playwright**
- **D-058:** **PROPOSED**
- **D-020:** **PROPOSED**

This is a review handoff, not a decision-promotion or merge record.

## What changed

T-604 adds one test-only shared behavioral matrix and two thin adapters:

```text
packages/browser-runtime/tests/
├── binding-driver-conformance.livewire.test.ts
├── binding-driver-conformance.htmx.test.ts
├── binding-driver-conformance.typecheck.ts
└── support/
    ├── binding-driver-conformance-suite.ts
    ├── livewire-conformance-adapter.ts
    └── htmx-conformance-adapter.ts
```

The shared suite is declared once and is executed unchanged against the production `LivewireBrowserDriver` and production `HtmxBrowserDriver`.

It covers only behavior common to both drivers:

- exact valid target dispatches once;
- foreign/invalid target fails closed;
- malformed/expired expiry fails closed;
- unknown/missing Action input fails closed;
- missing exact target is stale;
- equivalent replacement identity is never silently retargeted;
- already-aborted invocation performs zero framework dispatch.

It intentionally does not normalize driver-owned target structures, lifecycles, framework APIs, framework-specific error codes, post-dispatch cancellation behavior, or successful return values.

## Review focus

Please verify these exact points:

1. The same shared matrix, not duplicated case definitions, runs against both production drivers.
2. The matrix covers only genuinely common behavior and does not normalize target shapes, lifecycle, framework errors, cancellation internals, or success results.
3. Every fail-closed case proves zero unintended framework dispatch.
4. Equivalent replacement identities receive zero dispatch from an old binding.
5. The harness is test-only and does not duplicate either production driver.
6. Existing Livewire/HTMX driver-specific, cancellation, input-mapping, and WebMCP integration behavior remains covered by its original suites.
7. The real T-603 Chromium fixture remains green.
8. No T-701 runner, frozen-spec change, production refactor, workflow expansion, dependency expansion, or `tsconfig.json` change leaked into T-604.
9. `D-058` and `D-020` remain proposed until explicit post-review closure.

## TDD evidence

```text
Livewire RED head:          ae82c3d3bec6918a45932c3b11278b64b9ffe9d4
Livewire RED run:           34793105308 — browser tests failed after typecheck passed
Livewire GREEN head:        bbb594f91ce2983adf0652fdad12d0fc05ce7509
Livewire GREEN run:         34793156495 — browser job green

HTMX RED head:              7b04d5e04c6d97e51039c680ee78cba374e78413
HTMX RED run:               34793193056 — browser tests failed after typecheck passed
Complete GREEN head:        9d49c22ac2127c7ade6e235fae488f87490feb43
```

The RED entries were committed before their required support adapter existed. In each RED run, package typecheck succeeded first and the browser test step then failed. The GREEN runs add only the planned test support around the existing production driver.

## Verification evidence

Bound to implementation head `9d49c22ac2127c7ade6e235fae488f87490feb43`:

```text
Validate run:                   34793229849 — 7/7 jobs green
Contract validation:            PASS
TypeScript typecheck:           PASS
Livewire shared matrix:         11/11
HTMX shared matrix:             11/11
Shared conformance total:       22/22
Full browser runtime:           19 files / 319/319 tests
```

The full browser run includes the existing Livewire driver/cancellation/WebMCP suites, HTMX driver/input-mapping/cancellation/WebMCP suites, and RuntimeBinding expiry regression coverage.

### Fresh T-603 real-browser regression

Because `.github/workflows/htmx-fixture.yml` is intentionally path-filtered and T-604 changes only browser-runtime tests, its existing fixture job was explicitly rerun:

```text
Workflow/run:                  34758253003
Fresh job:                     103821380728 — SUCCESS
Checked-out fixture revision:  e98c919b90f9f19b58ae56b88e391f1abbb179e7
Playwright:                    8/8 passing in real Chromium
```

This is a fresh regression execution of the unchanged fixture revision. The T-604 branch diff contains no changes under `examples/htmx-prep-list/**`, `packages/browser-runtime/src/**`, or `.github/workflows/htmx-fixture.yml`.

## Scope audit

Implementation additions are limited to:

```text
packages/browser-runtime/tests/binding-driver-conformance.livewire.test.ts
packages/browser-runtime/tests/binding-driver-conformance.htmx.test.ts
packages/browser-runtime/tests/binding-driver-conformance.typecheck.ts
packages/browser-runtime/tests/support/binding-driver-conformance-suite.ts
packages/browser-runtime/tests/support/livewire-conformance-adapter.ts
packages/browser-runtime/tests/support/htmx-conformance-adapter.ts
```

The branch also contains the approved T-604 design/plan/tracking documentation.

Explicitly unchanged for T-604 implementation:

```text
packages/browser-runtime/src/**
spec/0.1/**
packages/laravel/src/**
examples/htmx-prep-list/**
.github/workflows/**
packages/browser-runtime/package*.json
packages/laravel/composer.*
packages/browser-runtime/tsconfig.json
```

## Decision boundary

- `D-058` remains **PROPOSED** pending external review and explicit closure.
- `D-020` remains **PROPOSED** pending a separate explicit portability decision after review.
- M6 remains **IN_PROGRESS**.
- T-701 remains future work.

Do not merge, promote decisions, or close M6 as part of this review request without an explicit follow-up gate.
