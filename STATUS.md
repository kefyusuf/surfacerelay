# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-browser-driver`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-601 — Explicit HTMX binding descriptor`
- **Current task:** `T-602 — HTMX browser driver`
- **T-602 state:** **IMPLEMENTED / EXTERNALLY REVIEWED / FINDINGS RESOLVED / MERGE PENDING**
- **Pull request:** `#11 — feat(htmx): add exact-source browser driver`
- **Current reviewed head before tracking closure:** `a5f1bd8e5bd64548d78b4a37411314af664e5eb3`
- **Current reviewed-head CI:** `34699961997` — **7/7 green**
- **Browser verification:** TypeScript typecheck + **297/297 Vitest** across 17 files
- **CodeRabbit:** **2 actionable inline findings; 2/2 resolved; 0 unresolved threads**
- **Accepted decisions:** `D-054`, `D-055`, `D-056`
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-603/T-604**
- **Next gate:** merge authorization for PR #11; do not begin T-603 automatically.

## Baseline entering T-602

```text
Base main:                    536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4
T-601 merge commit:           96581ff9d12dba5487b4831c3bc146081945decb
T-601 post-merge CI:          34657967629 — 7/7 green
Browser baseline:             174/174 Vitest + typecheck
PHP baseline:                 595 tests / 3164 assertions
```

Design and plan:

```text
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
docs/superpowers/plans/2026-09-12-htmx-browser-driver.md
Design checkpoint:            42cca940af35b3dff918a641b619d578f839047d / 34682081839 — 7/7 green
Plan checkpoint:              11f5a23cfd28f2b26feb13d880f2eb123b43154d / 34684333012 — 7/7 green
```

## Delivered T-602 behavior

### Exact HTMX 2.x execution boundary

```text
RuntimeBinding(driver=htmx, lifecycle=page)
        ↓
strict T-601 target parse + shared expiry
        ↓
exact sourceId resolution
        ↓
exact one physical hx-*/data-hx-* method/path
        ↓
same-origin + source-policy + busy + input checks
        ↓
host HTMX 2.x htmx.ajax()
```

The implementation uses only the host page's compatible HTMX 2.x runtime. It does not bundle HTMX, use raw `fetch()`, synthesize business output from HTML, or modify generic DriverRegistry/WebMCP contracts.

### Exact source and stale semantics

- exactly one `data-surfacerelay-htmx-source` match is required;
- zero or duplicate exact identities fail `binding_stale`;
- replacement/similar sources are never rediscovered or substituted;
- all 10 physical five-method `hx-*` / `data-hx-*` request forms are covered;
- method and raw path must exactly match the issued descriptor;
- trailing-slash/query-order/method drift fails stale;
- same-origin is checked before dispatch;
- later host hooks such as `htmx:configRequest` remain host behavior, not SurfaceRelay authorization/binding authority.

### Action input integrity

- only allowlisted own top-level Action-input names are accepted;
- required names must be own properties;
- strings, finite numbers, booleans and null map deterministically;
- arrays/plain objects are recursively validated and encoded under one top-level name;
- no dotted/bracket flattening;
- undefined/non-finite numbers/BigInt/Symbol/Function/Date/Map/Set/Blob/custom instances/accessors/non-enumerables/sparse arrays/cycles fail closed;
- repeated non-cyclic references are allowed;
- `__proto__` is preserved as an exact own mapped data property;
- `hasOwnProperty` is rejected because HTMX 2.x object-values processing calls that method on the values object;
- object/array encoding is performed over the validated own data-property graph and does not invoke inherited `Object.prototype.toJSON` / `Array.prototype.toJSON` hooks;
- ordinary host form/request state remains untrusted host state.

### Source policy and concurrency

Reference sources fail closed for `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, active source validation, and their applicable `data-hx-*` forms. Conservative ancestor scanning is intentional; SurfaceRelay does not reimplement HTMX inheritance/disinheritance.

Allowed ordinary host state includes form/hidden fields plus `hx-include`, `hx-headers`, `hx-request`, `hx-target`, `hx-swap`, and standard `hx-encoding`.

An exact source already carrying the HTMX request class fails `htmx_source_busy`. SurfaceRelay does not queue, replace, or broadly abort HTMX work.

### Cancellation and result semantics

- already-aborted/pre-dispatch invocations perform no HTMX dispatch and preserve the caller abort reason;
- synchronous `runtime.ajax()` / `htmx.ajax()` invocation is the dispatch frontier;
- post-frontier caller abort does not call `htmx:abort`, race/replace the promise, or claim network/server cancellation/rollback/reversal;
- natural HTMX success/failure wins after the frontier;
- successful execution resolves `undefined`;
- underlying HTMX/runtime rejection identity is preserved.

### Shared expiry

Strict RuntimeBinding RFC3339 classification moved into `runtime-binding-expiry.ts`. Livewire uses the shared helper while preserving the existing two invalid-expiry message branches and all existing cancellation/execution semantics.

## Implementation surface

Production/refactor:

```text
packages/browser-runtime/src/runtime-binding-expiry.ts
packages/browser-runtime/src/livewire-browser-driver.ts
packages/browser-runtime/src/htmx-errors.ts
packages/browser-runtime/src/htmx-browser-runtime.ts
packages/browser-runtime/src/htmx-input-mapping.ts
packages/browser-runtime/src/htmx-browser-driver.ts
```

Focused tests:

```text
packages/browser-runtime/tests/runtime-binding-expiry.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.test.ts
packages/browser-runtime/tests/htmx-browser-runtime.typecheck.ts
packages/browser-runtime/tests/htmx-input-mapping.test.ts
packages/browser-runtime/tests/htmx-browser-driver.test.ts
packages/browser-runtime/tests/htmx-cancellation.test.ts
packages/browser-runtime/tests/htmx-webmcp-integration.test.ts
```

Explicitly unchanged:

```text
packages/browser-runtime/src/types.ts
packages/browser-runtime/src/driver-registry.ts
packages/browser-runtime/src/webmcp-registration-lifecycle.ts
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/src/**
spec/0.1/**
examples/htmx/**
```

## TDD / verification evidence

```text
Expiry RED:                    acdfdd7486674b4374d02ef59837ca91ba5e7f7e
Expiry GREEN/refactor:         14c2e136ae3f8a21557132ee502a7e036892c540 / 0a13befca39a5c58cb29212b0574e68555e141f4
Runtime RED:                   a97323b16d3bca58147a8162dbeb2be41d9f5b05
Runtime GREEN/typecheck:       d7873bb64d6d22ab81593914676148638c7944cc / 3a0e1253de50d032f462fff0ed7d256a18548edd / e15fa915a31e61a461e3da6150d7b01baba35260
Input RED/GREEN:               dad9187d898bf3ce2e5e0a42d181cfb2b0d22c1c / f844f57e1b0a39eb1eda7652c4c3b8b28991d9aa
Driver RED/GREEN:              7310fa5fb3af6009f7106b899812f25df3abb16d / 612c3493ba493e1b4b760dd55adc5f1cb73da0dc
Policy RED/GREEN:              99b6247746254d1c4e86e5b73c6435914eb847bd / 27086b6094d3529bcc2e179742adeb7f9e55eccb
Cancellation proof:            54548b30c0ab1bb398c89cc3e343c887ac908461
WebMCP integration:            c887d64cec7f4f9c5c0475a44e5d3c8a3a0c4f3c
Special-key RED:               a06168befbc10010f8164c0515af0892ec154372 — 293 passed / exactly 2 failed
Special-key GREEN:             0d2021674e43c0ec4bf0a3e365e0915221b51e5e
External-review toJSON RED:    adde02f3c457169d9d2c47418b53d2d4413410be — 295 passed / exactly 2 failed
External-review toJSON GREEN:  2ec197213e966c96264b429be3e316c91c69c19e
Plan review alignment:         a5f1bd8e5bd64548d78b4a37411314af664e5eb3
Reviewed-head CI:              34699961997 — 7/7 green
Browser:                       17 files / 297/297 tests + typecheck
Contract:                      green
PHP lint:                      green
PHP matrix:                    4/4 green
```

Focused final counts:

```text
HTMX descriptor                 71/71
HTMX browser driver             49/49
HTMX input mapping              28/28
HTMX runtime                    20/20
HTMX cancellation                5/5
HTMX WebMCP integration          2/2
Shared expiry                   19/19
Livewire browser driver         33/33
Livewire cancellation           11/11
Livewire WebMCP                  2/2
```

## External review closure

PR #11 received one completed CodeRabbit review over the review-prep head with two actionable inline findings, both classified Minor:

1. inherited `toJSON` could alter a validated structured Action value during `JSON.stringify()`;
2. the implementation plan showed a combined Livewire invalid-expiry message inconsistent with the preserved production behavior.

Both were verified before modification. The serialization finding was reproduced RED with exactly two new failures, fixed with a prototype-hook-free deterministic encoder, and revalidated GREEN. The plan finding required no production change; the plan was aligned with the two existing Livewire error branches.

CodeRabbit explicitly confirmed both fixes in their threads. Both review threads are resolved and the current unresolved-thread count is **0**. A second complete CodeRabbit sweep was not available within the included hourly review quota; this is not represented as a second full review pass.

CodeRabbit also reports a generic docstring-coverage warning for touched functions. This is not a repository CI or contract gate, and no bulk JSDoc churn was added solely to satisfy that external heuristic.

## Decision state

- `D-054` — **ACCEPTED** for exact-source host HTMX 2.x execution only.
- `D-055` — **ACCEPTED** for busy-source failure and pre-ajax-only strong cancellation semantics.
- `D-056` — **ACCEPTED** for deterministic Action-input integrity and fail-closed reference-source policy.
- `D-020` — **PROPOSED**; no portability promotion before T-603/T-604.

## Known limitations / next proof

- HTMX 2.x only; unsupported major versions fail closed.
- T-602 tests use structural runtime/DOM fakes, not a real browser/server fixture.
- SurfaceRelay does not sandbox host `htmx:configRequest` or arbitrary host HTMX event handlers.
- No post-ajax generic network/server cancellation claim.
- `hasOwnProperty` cannot be a reference-driver Action input name because of HTMX 2.x object-values compatibility.
- T-603 must provide the real non-Laravel HTMX fixture; T-604 must prove shared Livewire/HTMX conformance before D-020 can be accepted.

## Current boundary

**T-602 is implemented and externally reviewed; all actionable review findings are resolved. PR #11 is merge-pending.** No merge/main revalidation has occurred yet. `D-020` remains PROPOSED, and T-603/T-604 are not started. The next allowed action is explicit merge authorization for PR #11.