# External Review / Merge Record — T-604 Shared Binding-Driver Conformance

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Task:** `T-604 — Shared conformance against Livewire + HTMX`
- **Milestone:** `M6 — HTMX Portability Proof` — **CLOSED**
- **Pull request:** `#13 — test(conformance): add shared Livewire/HTMX binding-driver matrix` — **MERGED**
- **Implementation head:** `9d49c22ac2127c7ade6e235fae488f87490feb43`
- **Implementation validate:** `34793229849` — **7/7 jobs green**
- **Shared matrix:** **22/22** — 11 Livewire + 11 HTMX
- **Browser:** **19 files / 319/319 Vitest + TypeScript typecheck**
- **Fresh real HTMX fixture regression:** job `103821380728` — **8/8 Playwright**
- **CodeRabbit:** **LOW merge risk; 2/2 actionable findings resolved; 0 unresolved**
- **Decision-promotion head:** `bfcbde90ba2892f2e192a1df8d5c7c84310b23c0`
- **Decision-promotion PR validate:** `34806672450` — **7/7 green**
- **D-058:** **ACCEPTED**
- **D-020:** **ACCEPTED**
- **Merge commit:** `2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080`
- **Post-merge main validate:** `34806790431` — **7/7 green**
- **Post-merge browser:** **19 files / 319/319 + typecheck**

## Reviewed implementation

T-604 adds a deliberately test-only portability proof:

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

It proves only behavior genuinely common to both drivers:

- exact valid target dispatches once;
- foreign/invalid target fails closed;
- malformed/expired expiry fails closed;
- unknown/missing Action input fails closed;
- missing exact target is stale;
- equivalent replacement identity is never silently retargeted;
- already-aborted invocation performs zero framework dispatch.

It intentionally does not normalize driver-owned target structures, lifecycles, runtime APIs, framework-specific error codes, post-dispatch cancellation behavior, or successful return values.

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

Each RED commit added the new test entry before its required test support existed. Typecheck passed and the browser test step failed, proving collection before the corresponding adapter/harness was supplied.

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

### Real HTMX regression

The T-603 fixture workflow is intentionally path-filtered and T-604 does not change production browser-runtime source or the fixture. The existing real-browser fixture job was therefore explicitly rerun as regression evidence:

```text
Workflow/run:                  34758253003
Fresh job:                     103821380728 — SUCCESS
Checked-out fixture revision:  e98c919b90f9f19b58ae56b88e391f1abbb179e7
Playwright:                    8/8 passing in real Chromium
```

The branch contains no changes under `examples/htmx-prep-list/**`, `packages/browser-runtime/src/**`, or `.github/workflows/htmx-fixture.yml`.

## CodeRabbit review closure

CodeRabbit reviewed the substantive PR through `e71248adb7afbce4c286b4be2504344eb3553da2` and classified merge risk as **LOW**.

It raised exactly two actionable findings, both documentation consistency issues:

1. the design spec still presented its design-time `NOT STARTED` state as current;
2. `STATUS.md` did not list unresolved `D-058` / `D-020` under the repository-required `Needs decision` section.

Fix and recheck evidence:

```text
Design-state fix:              26324a20a640940d138c5954351133276fb4f4fb
Needs-decision fix:            d8396e36b5e335f89f719f425597cd19ac30a7f3
Review-fix validate:           34803374097 — 7/7 green
Review threads:                2/2 reviewer-confirmed resolved / 0 unresolved
```

A second full CodeRabbit sweep of only the two docs-only fix commits was requested, but CodeRabbit reported its included OSS review capacity had been exhausted. This limitation was recorded rather than represented as a completed review. The substantive implementation had already been reviewed, and each actionable finding was directly rechecked and resolved by the same reviewer.

The CodeRabbit docstring-coverage item remained a generic pre-merge metric warning, not an inline correctness finding or repository requirement. Scope was not expanded solely to satisfy that external metric.

## Closure decisions

### D-058 — ACCEPTED

Livewire and HTMX establish shared browser binding-driver conformance through one executable test matrix over their genuine overlap: fail-closed target validation, expiry classification, Action-input mappability, exact-target stale/no-retarget semantics, dispatch/no-dispatch evidence, and cancellation before framework dispatch.

Acceptance is bounded to this tested overlap. It does not require identical driver targets, lifecycle values, runtime APIs, framework-specific errors, post-dispatch cancellation semantics, or success results, and it does not create the future T-701 general conformance runner.

### D-020 — ACCEPTED

HTMX is the materially different second binding used to establish portability. T-601–T-603 prove a page/source/path/named-input model and real non-Laravel HTMX 2.x browser execution materially different from Livewire's component/positional/action-interception model. T-604 then proves both production drivers satisfy the same shared binding-driver matrix.

This satisfies the D-009 two-materially-different-bindings + shared-scenarios threshold. It does not by itself authorize extracting or publishing a standalone cross-framework specification.

## Merge / main revalidation

```text
Decision-promotion head:        bfcbde90ba2892f2e192a1df8d5c7c84310b23c0
Decision-promotion PR validate: 34806672450 — 7/7 green
Merge commit:                   2b25b5ccfbbdc9bf8a9e757f93f2cc59fe9ea080
Post-merge main validate:       34806790431 — 7/7 green
Post-merge browser:             19 files / 319/319 + typecheck
```

## Scope audit

T-604 production behavior was not refactored to manufacture portability. Implementation additions remained limited to `packages/browser-runtime/tests/**` plus design/plan/tracking documentation.

Explicitly unchanged by the T-604 implementation:

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

## Boundary after closure

T-604 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED** and M6 is **CLOSED**.

`D-058` and `D-020` are accepted. T-701 remains `TODO`; no M7 implementation starts automatically and a new explicit user gate is required.