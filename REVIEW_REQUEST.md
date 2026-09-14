# External Review Record — T-604 Shared Binding-Driver Conformance

## Review status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/binding-driver-conformance`
- **Task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Pull request:** `#13 — test(conformance): add shared Livewire/HTMX binding-driver matrix` — **OPEN**
- **State:** **IMPLEMENTED / VERIFIED / REVIEWED / READY FOR CLOSURE DECISION**
- **Implementation head:** `9d49c22ac2127c7ade6e235fae488f87490feb43`
- **Implementation validate:** `34793229849` — **7/7 jobs green**
- **Browser:** **19 files / 319/319 Vitest + TypeScript typecheck**
- **Shared matrix:** **22/22** — 11 Livewire + 11 HTMX
- **Fresh real HTMX fixture rerun:** run `34758253003`, job `103821380728` — **8/8 Playwright**
- **Initial review head:** `e71248adb7afbce4c286b4be2504344eb3553da2`
- **Review-fix head:** `d8396e36b5e335f89f719f425597cd19ac30a7f3`
- **Review-fix validate:** `34803374097` — **7/7 jobs green**
- **CodeRabbit:** **LOW merge risk; 2/2 actionable findings confirmed addressed and resolved; 0 unresolved**
- **D-058:** **PROPOSED**
- **D-020:** **PROPOSED**

This file records the completed external-review gate. It is not a decision-promotion, merge, or M6-closure record.

## Reviewed implementation

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

The shared suite is declared once and executed unchanged against the production `LivewireBrowserDriver` and production `HtmxBrowserDriver`.

It proves only behavior common to both drivers:

- exact valid target dispatches once;
- foreign/invalid target fails closed;
- malformed/expired expiry fails closed;
- unknown/missing Action input fails closed;
- missing exact target is stale;
- equivalent replacement identity is never silently retargeted;
- already-aborted invocation performs zero framework dispatch.

It does not normalize driver-owned target structures, lifecycles, framework APIs, framework-specific error codes, post-dispatch cancellation behavior, or successful return values.

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

The RED entries were committed before their required support adapter existed. In each RED run, package typecheck succeeded first and the browser test step then failed. The GREEN changes supplied only the planned test support around the existing production driver.

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

Because `.github/workflows/htmx-fixture.yml` is path-filtered and T-604 changes only browser-runtime tests, the existing fixture job was explicitly rerun:

```text
Workflow/run:                  34758253003
Fresh job:                     103821380728 — SUCCESS
Checked-out fixture revision:  e98c919b90f9f19b58ae56b88e391f1abbb179e7
Playwright:                    8/8 passing in real Chromium
```

This is a fresh regression execution of the unchanged fixture revision. The T-604 branch diff contains no changes under `examples/htmx-prep-list/**`, `packages/browser-runtime/src/**`, or `.github/workflows/htmx-fixture.yml`.

## CodeRabbit external review

CodeRabbit reviewed the complete substantive PR through `e71248adb7afbce4c286b4be2504344eb3553da2` and classified merge risk as **LOW**.

It raised exactly two actionable findings, both documentation consistency issues and neither a production/test behavior defect:

1. `docs/superpowers/specs/2026-09-13-binding-driver-conformance-design.md` still presented the design-time `NOT STARTED` state as current;
2. `STATUS.md` did not list unresolved `D-058` / `D-020` under the `Needs decision` section required by `AGENTS.md`.

Fix evidence:

```text
Design-state fix:              26324a20a640940d138c5954351133276fb4f4fb
Needs-decision fix:            d8396e36b5e335f89f719f425597cd19ac30a7f3
Review-fix validate:           34803374097 — 7/7 jobs green
Review threads:                2/2 resolved / 0 unresolved
```

CodeRabbit re-ran targeted verification in each original thread, explicitly confirmed both fixes as addressed, and resolved both review threads.

A second full review of only the two docs-only fix commits was requested. CodeRabbit reported that its included OSS review capacity had been exhausted and did not perform that second full sweep. This limitation is recorded explicitly; it is not treated as a completed review. The substantive implementation had already been fully reviewed, and each resulting actionable finding was independently rechecked by the same reviewer after its fix.

The CodeRabbit docstring-coverage item remains a generic pre-merge metric warning rather than an inline correctness finding or repository requirement. T-604 adds test-only harness helpers and no new public production API; scope was not expanded solely to satisfy that external metric.

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

The branch also contains approved T-604 design/plan/tracking documentation and documentation-only review fixes/records.

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

## Review conclusion

The review gate has no unresolved correctness findings:

- shared matrix design remained test-only;
- no production contract was broadened;
- fail-closed/no-retarget behavior remained explicit;
- verification stayed green;
- both actionable documentation findings were reviewer-confirmed and resolved.

## Decision boundary

- `D-058` remains **PROPOSED** pending explicit closure authorization.
- `D-020` remains **PROPOSED** pending a separate explicit portability decision.
- M6 remains **IN_PROGRESS**.
- PR #13 remains **OPEN**.
- T-701 remains future work.

Do not merge, promote decisions, or close M6 without the explicit next gate.