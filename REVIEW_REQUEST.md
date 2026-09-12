# External Review Request — T-603 Non-Laravel HTMX Fixture

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-603 — Non-Laravel HTMX fixture app`
- **Branch:** `feat/htmx-fixture`
- **Base:** `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- **Verified fixture implementation head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Validate workflow on verified head:** `34719068926` — **7/7 green**
- **Real-browser fixture workflow on verified head:** `34719068934` — **1/1 green**
- **Playwright:** **8/8 real Chromium tests**
- **Browser-runtime regression:** **17 files / 297/297 Vitest + TypeScript typecheck**
- **Decision:** `D-057` — **ACCEPTED for verified T-603 fixture behavior**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Pull request:** not created yet
- **Merge:** not performed

## What T-603 adds

A real non-Laravel HTMX reference fixture that exercises the merged T-601/T-602 implementation through an actual browser, actual HTMX runtime, actual HTTP mutation, and actual DOM swap.

Core path:

```text
prep_list.add_item@1
  -> Node 22 page render
  -> server-issued page RuntimeBinding
  -> real htmx.org 2.0.10
  -> human click OR HtmxBrowserDriver.execute()
  -> POST /items
  -> same in-memory server mutation
  -> same HTML fragment
  -> same hx-target / hx-swap
```

The fixture does not add a second agent-only endpoint, raw-fetch path, driver copy, Node ActionBus clone, or cross-driver conformance claim.

## Review focus

Please review these boundaries specifically:

1. **Same business path:** human and agent must both mutate only through `POST /items`.
2. **Real runtime:** the fixture must use the actual merged browser-runtime source compiled to browser ESM, not copied logic.
3. **Real HTMX:** the browser must run pinned `htmx.org@2.0.10` and produce actual `HX-Request` traffic.
4. **Input precedence:** SurfaceRelay Action input must override a stale same-name form field while unrelated host state remains present.
5. **Page identity:** reload must renew `sourceId` and `bindingId` together.
6. **No retargeting:** an equivalent replacement source must not inherit the old binding; stale execution must send zero `/items` requests.
7. **Server-state proof:** human and agent mutations must survive full-page reload.
8. **Test-only boundary:** `POST /__test/reset` may reset state but must never provide a second business mutation path.
9. **Bridge boundary:** browser test bridge may delegate to `HtmxBrowserDriver.execute()` and perform DOM-only replacement, but must not call `fetch`, XHR, or `htmx.ajax()` directly.
10. **Hardening:** bounded body, duplicate/missing/invalid input rejection, wrong-method handling, traversal-safe runtime serving, HTML escaping, and script-safe binding JSON.
11. **Dependency scope:** HTMX/Playwright/TypeScript fixture dependencies must stay isolated to the fixture package.
12. **Repository scope:** production browser-runtime, Laravel, frozen `spec/0.1/**`, existing ActionDefinition, and `validate.yml` must stay unchanged.
13. **Decision scope:** D-057 may be accepted for this verified fixture, but D-020 must remain proposed until T-604.

## Executable proof matrix

The fixture suite contains eight real Chromium tests:

```text
1. HTMX 2.0.10 bootstrap + exact server-issued binding/source identity
2. normal human HTMX POST /items + swap + reload persistence
3. SurfaceRelay production driver + Action override + host-state preservation
4. human/agent normalized real-request convergence
5. full page reload renews sourceId + bindingId
6. equivalent real-DOM replacement -> binding_stale + zero /items dispatch
7. invalid/duplicate/oversized/traversal cases fail closed
8. user-supplied item names remain text-safe in partial + full-page rendering
```

Key real-wire proof:

```text
DOM form before agent call: name=stale-human-value
SurfaceRelay Action input:  name=agent-tea
actual request body:        name=agent-tea
ordinary host field:        uiContext=prep-list
```

## TDD evidence

```text
Bootstrap RED:              a61e2899532706059fa76ee38ea781732257da9d / 34710117812
Bootstrap GREEN:            a436bd2a9a4032877b45218a6cd6a35e801a1d07 / 34710621196

Human-path RED:             6c11ada77b26fcfe0eddd5940e63f3cecf2af4f2 / 34710804593
Human-path GREEN:           3a7e6de8777dad5b3889d390df55259bef2e60f6 / 34710965945

Driver-path RED:            ae2dcd4dab234ef005dd94875a2cc1476e127bab / 34711093202
RED result:                 3 total / 2 passed / exactly 1 failed (`addItem` absent)
Driver-path GREEN:          e154743cfc8509ec22889cff49581c85ebbbb632 / 34717781774

Convergence/lifecycle:      28f79fc9dcd172254d4d3ecd3d16e1aa3c4e1d81 / 34717961616 — 5/5 green

Hardening RED:              d0733055514ddef42907bbcd26018aa3bfef1f63 / 34718189031
RED result:                 8 total / 6 passed / exactly 2 failed
Hardening GREEN:            06a261636cd45fac2d95fc54228a2902f25c0bf1 + 71262bee0f5f011a87d2f1fb3216ebc528e9241e
Hardening verification:     34718699712 — 8/8 green
```

## Verified implementation evidence

Exact fixture implementation head:

```text
31c0b85af56aaa06cac4efc2bced36bee0befcc2
```

Repository validation:

```text
Run:                         34719068926
Jobs:                        7/7 green
Browser typecheck:           green
Browser tests:               17 files / 297/297
Contract:                    green
PHP lint:                    green
PHP compatibility matrix:   green
```

Real fixture validation:

```text
Run:                         34719068934
Jobs:                        1/1 green
Browser:                     Chromium
Playwright:                  8/8 passed
```

## Expected change surface

```text
.github/workflows/htmx-fixture.yml
examples/htmx-prep-list/package.json
examples/htmx-prep-list/package-lock.json
examples/htmx-prep-list/tsconfig.runtime.json
examples/htmx-prep-list/server.mjs
examples/htmx-prep-list/client.mjs
examples/htmx-prep-list/playwright.config.mjs
examples/htmx-prep-list/README.md
examples/htmx-prep-list/tests/prep-list.spec.mjs

docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Explicitly unchanged:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

No `.tmp`, `node_modules`, Chromium binary, Playwright trace, screenshot, or test-result artifact is committed.

## Current boundary

T-603 is **implemented, self-reviewed, and ready for external review**. This review-prep tracking is intentionally docs-only. The expensive path-filtered real-browser proof remains attached to verified fixture implementation head `31c0b85af56aaa06cac4efc2bced36bee0befcc2`; the final review-prep head must receive a fresh normal `validate` run and a diff proof showing no fixture/runtime/workflow input changed after that verified head.

Do not create/merge a PR or begin T-604 without the next explicit user gate. `D-020` remains PROPOSED.