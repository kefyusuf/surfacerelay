# External Review Request — T-602 HTMX Browser Driver

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-602 — HTMX browser driver`
- **Branch:** `feat/htmx-browser-driver`
- **Base:** `main@536a89f7a7fe6fb10f4284203cffb5a63ecc0fb4`
- **Verified implementation head:** `0d2021674e43c0ec4bf0a3e365e0915221b51e5e`
- **Implementation CI:** `34695125241` — **7/7 green**
- **Browser:** TypeScript typecheck + **295/295 Vitest** across 17 files
- **Decisions:** `D-054`, `D-055`, `D-056` — **ACCEPTED for verified T-602 reference-driver behavior**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-603/T-604**
- **PR:** not created yet
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

## Review focus

Please concentrate on these risks:

1. **Exact identity / stale handling** — any path that could substitute a similar/replacement source, tolerate duplicate `sourceId`, or normalize changed method/path instead of failing stale.
2. **Physical HTMX request ambiguity** — the driver requires exactly one of the 10 supported physical request attributes (five verbs × `hx-*`/`data-hx-*`). Check missing/duplicate/method/path drift handling.
3. **Origin boundary** — SurfaceRelay checks the path it passes to HTMX; later `htmx:configRequest` or other host handlers are intentionally outside the SurfaceRelay sandbox. Check that no stronger security claim is implied.
4. **Input coercion / object hazards** — inspect unknown/required own-key handling, nested JSON validation, accessors, non-enumerables, sparse arrays, cycles, custom instances, `__proto__`, and the deliberate `hasOwnProperty` rejection required by HTMX 2.x object-values conversion.
5. **HTMX source-policy escape paths** — inspect conservative source/ancestor rejection for `hx-vals`, `hx-vars`, restrictive `hx-params`, `hx-confirm`, `hx-prompt`, `hx-sync`, `hx-indicator`, `hx-ext`, and active source validation, including `data-hx-*` forms.
6. **Busy-source truthfulness** — look for any path where a currently active exact source could enter HTMX's queue/replace/abort semantics and make `execute()` resolve before the intended request actually runs.
7. **Cancellation frontier** — no-dispatch guarantee is only before `runtime.ajax()`/`htmx.ajax()` invocation. Post-frontier abort must not call `htmx:abort`, race the promise, or replace natural framework success/failure.
8. **Shared expiry regression** — strict RFC3339 parsing moved out of Livewire. Verify Livewire behavior/type compatibility is unchanged.
9. **Generic contract drift** — `BindingDriver`, `RuntimeBinding`, `DriverRegistry`, and WebMCP lifecycle should remain unchanged; HTMX integration is proved through existing seams.
10. **Scope creep** — confirm no dependency files, `packages/laravel/src/**`, `spec/0.1/**`, T-603 fixture, or T-604 shared-conformance work entered T-602.

## Verification evidence

```text
Design checkpoint:                 42cca940af35b3dff918a641b619d578f839047d / 34682081839 — 7/7 green
Plan gate:                         11f5a23cfd28f2b26feb13d880f2eb123b43154d / 34684333012 — 7/7 green
Expiry RED:                        acdfdd7486674b4374d02ef59837ca91ba5e7f7e
Runtime RED:                       a97323b16d3bca58147a8162dbeb2be41d9f5b05
Input RED:                         dad9187d898bf3ce2e5e0a42d181cfb2b0d22c1c
Driver-core RED:                   7310fa5fb3af6009f7106b899812f25df3abb16d
Source-policy RED:                 99b6247746254d1c4e86e5b73c6435914eb847bd
Special-key hardening RED:         a06168befbc10010f8164c0515af0892ec154372 — exactly 2/295 failed
Verified implementation head:     0d2021674e43c0ec4bf0a3e365e0915221b51e5e
Implementation CI:                34695125241 — 7/7 green
Browser final:                    295/295 + typecheck
Contract / PHP lint / PHP matrix: green
```

Focused browser counts:

```text
HTMX descriptor                 71/71
HTMX browser driver             49/49
HTMX input mapping              26/26
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

## Boundary after review

Do not infer T-603/T-604 completion from this task. Even if T-602 review is clean, `D-020` remains PROPOSED until a real non-Laravel HTMX fixture and shared Livewire/HTMX conformance prove the portability claim.