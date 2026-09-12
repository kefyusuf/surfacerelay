# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-fixture`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-602 — HTMX browser driver`
- **Current task:** `T-603 — Non-Laravel HTMX fixture app`
- **T-603 state:** **IMPLEMENTED / EXTERNAL REVIEW IN PROGRESS**
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof`
- **Original verified fixture head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Review-fix executable head:** `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`
- **Review-fix validate:** `34725480849` — **7/7 green**
- **Review-fix real fixture:** `34725480861` — **1/1 green; 8/8 Playwright**
- **Browser-runtime regression:** TypeScript typecheck + **297/297 Vitest across 17 files**
- **CodeRabbit:** one completed review; **4 actionable + 1 nitpick** initially; executable findings addressed, tracking closure in progress
- **Fixture decision:** `D-057` — **ACCEPTED**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Next gate:** finish review-thread closure and exact-head verification; merge only after explicit authorization.

## Baseline entering T-603

```text
Base main:                    83df122d84f6881a4a3541bd5c72e1108e5d1e48
T-602 final feature head:     ebad0f04a3b540a5a1536350038d0919ff600ebd
T-602 merge commit:           ee9af986f22cba45b05c59059c11e68ac46111fd
T-602 final closure CI:       34702679885 — 7/7 green
Browser baseline:             297/297 + typecheck
```

Accepted T-602 decisions entering T-603: `D-054`, `D-055`, `D-056`.

## T-603 delivered proof

T-603 adds one deliberately small real non-Laravel fixture:

```text
existing prep_list.add_item@1 ActionDefinition
        ↓
plain Node 22 HTTP application
        ↓
server-issued page-scoped HTMX RuntimeBinding
        ↓
real htmx.org 2.0.10 in Chromium
        ↓
human click OR merged HtmxBrowserDriver.execute()
        ↓
POST /items
        ↓
same in-memory server mutation
        ↓
same HTML fragment
        ↓
same source-defined hx-target / hx-swap
```

### Fixture boundaries

- independent private package under `examples/htmx-prep-list/`;
- Node 22 `node:http`, bound only to `127.0.0.1:4173`;
- pinned `htmx.org@2.0.10`, `@playwright/test@1.63.0`, TypeScript `5.9.3`;
- actual `packages/browser-runtime/src/**/*.ts` compiled into ignored `.tmp/runtime/` ESM;
- exact existing `examples/prep-list/action.add-item.json` reused;
- every page render creates fresh `sourceId` + `bindingId` through production `createHtmxBindingTarget()`;
- exactly one business mutation route: `POST /items`;
- exactly one test-only state endpoint: `POST /__test/reset`;
- client bridge delegates only to production `HtmxBrowserDriver.execute()` and a DOM-only replacement helper;
- real network assertions prove `HX-Request`, method/path/content-type/form values;
- body is bounded to 16 KiB and duplicate/missing/invalid names fail closed;
- runtime static serving accepts only flat emitted JS filenames;
- item rendering and embedded binding JSON are context-escaped;
- no database, Node ActionBus clone, Laravel trust-control clone, or T-604 shared-conformance behavior.

### Real browser proof matrix

Eight real Chromium tests prove:

```text
1. HTMX 2.0.10 bootstrap + exact server-issued binding/source identity
2. human HTMX POST /items + swap + reload persistence
3. SurfaceRelay driver path + stale same-name form override + host-state preservation
4. normalized human/agent real-request convergence
5. page reload renews sourceId and bindingId together
6. equivalent real-DOM replacement makes old binding stale with zero /items request
7. invalid/duplicate/oversized/media-type/traversal requests fail closed
8. item content remains text in partial and full-page rendering
```

## TDD / implementation evidence

```text
Design spec:                     docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
Implementation plan:             docs/superpowers/plans/2026-09-12-htmx-fixture.md
Plan checkpoint:                 678918d67c8ff33c09efdd2d2de196dab7cb6b4c / 34708675763 — 7/7 green

Bootstrap RED:                   a61e2899532706059fa76ee38ea781732257da9d
Bootstrap RED proof:             34710117812 — server.mjs missing after deps/runtime emit succeeded
Bootstrap GREEN:                 a436bd2a9a4032877b45218a6cd6a35e801a1d07 / 34710621196

Human-path RED:                  6c11ada77b26fcfe0eddd5940e63f3cecf2af4f2
Human-path RED proof:            34710804593 — reset endpoint 404
Human-path GREEN:                3a7e6de8777dad5b3889d390df55259bef2e60f6 / 34710965945

Driver-path RED:                 ae2dcd4dab234ef005dd94875a2cc1476e127bab
Driver-path RED proof:           34711093202 — 2 passed / exactly 1 failed (`addItem` absent)
Driver-path GREEN:               e154743cfc8509ec22889cff49581c85ebbbb632 / 34717781774

Convergence/lifecycle proof:      28f79fc9dcd172254d4d3ecd3d16e1aa3c4e1d81 / 34717961616 — 5/5 green

Hardening RED:                   d0733055514ddef42907bbcd26018aa3bfef1f63
Hardening RED proof:             34718189031 — 8 total / 6 passed / exactly 2 failed
Hardening GREEN:                 06a261636cd45fac2d95fc54228a2902f25c0bf1 + 71262bee0f5f011a87d2f1fb3216ebc528e9241e
Hardening verification:          34718699712 — 8/8 green

Original verified fixture head:  31c0b85af56aaa06cac4efc2bced36bee0befcc2
Validate:                        34719068926 — 7/7 green
HTMX fixture:                    34719068934 — 1/1 green / 8/8 Playwright
Browser regression:              17 files / 297/297 + typecheck
Review-prep head:                0348bb6b50a6a8f36c4b3f1315515dd6e9213c87
Review-prep validate:            34722974304 — 7/7 green
Review-prep diff proof:          31c0b85a..0348bb6b — tracking files only
```

## External review — PR #12

CodeRabbit completed one review and initially raised **4 actionable comments + 1 nitpick**.

### Finding A — workflow token permissions / persisted checkout credentials — FIXED

Two inline comments represented the same security boundary:

```text
permissions: contents: read
actions/checkout@v4 -> persist-credentials: false
```

Review-fix head evidence confirms the workflow token is read-only and checkout credentials are removed before npm-controlled execution.

### Finding B — near-miss Content-Type accepted — RED / GREEN FIXED

Original code used:

```text
contentType.startsWith('application/x-www-form-urlencoded')
```

so `application/x-www-form-urlencoded-invalid` incorrectly reached mutation and returned `201`.

```text
RED commit:        da528e4db76af7c04a351c99a20df2e2da7387dd
RED fixture run:   34725182292 — 8 total / 7 passed / exactly 1 failed
RED failure:       expected 415, received 201
GREEN commit:      226f8169ee7cf251a079cd9f763e21791d49b39a
Review-fix head:   7284dd21292c17fc35ddfd3bfb045d50e3dab38c
Fixture run:       34725480861 — 8/8 green
Validate run:      34725480849 — 7/7 green
```

The server now parses the media type before parameters and requires exact `application/x-www-form-urlencoded` equality.

### Finding C — review-prep readiness proof — VERIFIED / RECORD ALIGNMENT

The finding was based on the tracking text available to the reviewer. The original review-prep head had already received:

```text
0348bb6b50a6a8f36c4b3f1315515dd6e9213c87
validate 34722974304 — 7/7 green
31c0b85a..0348bb6b diff — STATUS.md, TASKS.md, REVIEW_REQUEST.md, DECISION-REGISTER only
```

The later executable review fixes intentionally supersede that review-prep head. Current records therefore use `7284dd21...` as the executable review-fix head and require a final docs-only closure-head validation before merge.

### Nitpick — REVIEW_REQUEST handoff too long — FIXED

Detailed historical RED/GREEN evidence remains here and in `TASKS.md`. `REVIEW_REQUEST.md` is reduced to current review state, focus, verified heads/results, scope, and next gate.

## Change surface

Fixture/runtime proof surface:

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
```

Design/tracking surface:

```text
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

Explicitly unchanged by T-603:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package.json
packages/browser-runtime/package-lock.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

## Decision state

- `D-057` — **ACCEPTED** for the verified real non-Laravel HTMX fixture proof.
- `D-054`, `D-055`, `D-056` remain accepted.
- `D-020` remains **PROPOSED**; T-604 shared conformance is still required.

## Current boundary

**T-603 is implemented and external review is in progress on PR #12.** Executable review fixes are verified at `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`. The next step is review-thread closure plus a final docs-only review-closure head with fresh normal `validate` and diff proof. Do not merge or begin T-604 without the next explicit user gate.
