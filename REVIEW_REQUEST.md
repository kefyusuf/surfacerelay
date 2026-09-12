# External Review Record — T-602 HTMX Browser Driver

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-602 — HTMX browser driver`
- **Pull request:** `#11 — feat(htmx): add exact-source browser driver` — **MERGED**
- **Base entering T-602:** `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4`
- **Pre-review implementation head:** `0d2021674e43c0ec4bf0a3e365e0915221b51e5e`
- **Review-aligned head:** `a5f1bd8e5bd64548d78b4a37411314af664e5eb3`
- **Final feature head:** `ebad0f04a3b540a5a1536350038d0919ff600ebd`
- **Final feature CI:** `34700403577` — **7/7 green**
- **Merge commit:** `ee9af986f22cba45b05c59059c11e68ac46111fd`
- **Post-merge main CI:** `34701911188` — **7/7 green**
- **Browser on main:** TypeScript typecheck + **297/297 Vitest** across 17 files
- **CodeRabbit findings:** **2 actionable inline findings; 2/2 resolved; 0 unresolved threads**
- **Decisions:** `D-054`, `D-055`, `D-056` — **ACCEPTED for verified T-602 reference-driver behavior**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-603/T-604**
- **T-603:** not started

## What T-602 adds

A fail-closed HTMX 2.x browser driver that consumes the T-601 descriptor and executes the exact current human-facing source through the host page's existing `htmx.ajax()` runtime.

Core flow:

```text
RuntimeBinding(driver=htmx, lifecycle=page)
  -> strict descriptor + shared expiry
  -> exactly one sourceId match
  -> exactly one physical hx-*/data-hx-* method/path
  -> exact raw drift + same-origin checks
  -> source policy + busy gate
  -> deterministic Action-input mapping
  -> pre-dispatch AbortSignal check
  -> host HTMX 2.x ajax()
```

No raw `fetch()`, second HTMX runtime, HTML-to-business-result synthesis, Laravel production coupling, frozen contract change, DOM emulator, or browser-automation dependency was added.

## Completed review focus

The external review concentrated on:

1. exact identity/stale handling and no replacement retargeting;
2. all 10 physical five-verb HTMX request attributes and ambiguity/drift;
3. same-origin pre-dispatch boundary vs later host HTMX hooks;
4. input coercion/object hazards including accessors, sparse arrays, cycles, `__proto__`, `hasOwnProperty`, and prototype serialization hooks;
5. conservative `hx-*` / `data-hx-*` source-policy escape paths and ancestors;
6. busy-source truthfulness;
7. pre-vs-post `htmx.ajax()` cancellation semantics;
8. shared expiry regression against Livewire;
9. unchanged generic `BindingDriver` / RuntimeBinding / DriverRegistry / WebMCP contracts;
10. dependency/Laravel/spec/T-603/T-604 scope boundaries.

## CodeRabbit findings and closure

### Finding 1 — inherited `toJSON` could rewrite validated structured input — FIXED / CONFIRMED

The original structured encoder validated plain object/array values and then called `JSON.stringify(value)`. An inherited `Object.prototype.toJSON` or `Array.prototype.toJSON` could therefore replace already-validated content during serialization.

TDD proof:

```text
RED commit:        adde02f3c457169d9d2c47418b53d2d4413410be
RED CI:            34699094234
Browser result:    297 total — 295 passed / exactly 2 failed
Failures:          Object.prototype.toJSON and Array.prototype.toJSON rewrote validated input

GREEN commit:      2ec197213e966c96264b429be3e316c91c69c19e
```

The fix recursively serializes only the already-validated own enumerable data-property graph. `JSON.stringify()` is no longer called on object/array containers, so inherited `toJSON` hooks cannot alter the request. Primitive string quoting still uses JSON string escaping.

CodeRabbit explicitly confirmed the fix in-thread and the thread is resolved.

### Finding 2 — plan example collapsed two preserved Livewire expiry errors — FIXED / CONFIRMED

Production code already preserved the prior Livewire distinction:

```text
non-string expiresAt
  -> Livewire binding expiresAt must be an RFC3339 string or null.

invalid RFC3339 string
  -> Livewire binding expiresAt is not a valid RFC3339 date-time.
```

The implementation plan showed one combined message despite its own no-behavior-change constraint. The plan was corrected to reflect the existing two branches; no production behavior change was required.

```text
Plan alignment commit: a5f1bd8e5bd64548d78b4a37411314af664e5eb3
```

CodeRabbit explicitly confirmed the correction in-thread and the thread is resolved.

### Non-blocking CodeRabbit heuristic

CodeRabbit also reported a generic docstring-coverage warning for touched functions. SurfaceRelay's repository CI and contract validation do not enforce that external heuristic. No bulk JSDoc churn was introduced solely to satisfy it.

A second complete CodeRabbit sweep was not available within the included hourly review quota. This record therefore claims one completed full review plus explicit per-thread rechecks/confirmations, not two full review passes.

## Verification evidence

```text
Design checkpoint:                 42cca940af35b3dff918a641b619d578f839047d / 34682081839 — 7/7 green
Plan gate:                         11f5a23cfd28f2b26feb13d880f2eb123b43154d / 34684333012 — 7/7 green
Pre-review implementation head:   0d2021674e43c0ec4bf0a3e365e0915221b51e5e / 34695125241 — 7/7 green
Pre-review browser:                295/295 + typecheck
External-review RED:               adde02f3c457169d9d2c47418b53d2d4413410be / 34699094234 — 295 passed / exactly 2 failed
External-review GREEN:             2ec197213e966c96264b429be3e316c91c69c19e
Review-aligned head:               a5f1bd8e5bd64548d78b4a37411314af664e5eb3 / 34699961997 — 7/7 green
Final feature head:                ebad0f04a3b540a5a1536350038d0919ff600ebd / 34700403577 — 7/7 green
Merge commit:                      ee9af986f22cba45b05c59059c11e68ac46111fd
Post-merge main CI:                34701911188 — 7/7 green
Browser on main:                   17 files / 297/297 + typecheck
Contract / PHP lint / PHP matrix: green
Review threads:                    2/2 resolved / 0 unresolved
```

Focused browser counts:

```text
HTMX descriptor                 71/71
HTMX browser driver             49/49
HTMX input mapping              28/28
HTMX runtime                    20/20
HTMX cancellation                5/5
HTMX WebMCP integration          2/2
Shared expiry                   19/19
Livewire driver                 33/33
Livewire cancellation           11/11
Livewire WebMCP                  2/2
```

## Expected change surface

```text
STATUS.md
TASKS.md
REVIEW_REQUEST.md
docs/DECISION-REGISTER.md
docs/superpowers/specs/2026-09-12-htmx-browser-driver-design.md
docs/superpowers/plans/2026-09-12-htmx-browser-driver.md
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

## Boundary after review and merge

T-602 is **externally reviewed, merged, and revalidated on `main`**. Do not infer T-603/T-604 completion from this task. `D-020` remains PROPOSED until a real non-Laravel HTMX fixture and shared Livewire/HTMX conformance prove the portability claim.

The next gate is explicit authorization to start T-603.