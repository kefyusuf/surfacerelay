# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `feat/htmx-fixture`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-602 — HTMX browser driver`
- **Current task:** `T-603 — Non-Laravel HTMX fixture app`
- **T-603 state:** **IMPLEMENTED / EXTERNALLY REVIEWED / MERGE READY**
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof`
- **Original verified fixture head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Review-fix executable head:** `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`
- **Final reviewed head before closure record:** `04f98b9f02c5012d396ece0641e358221ecd92c9`
- **Final reviewed validate:** `34725996245` — **7/7 green**
- **Final reviewed real fixture:** `34725996254` — **1/1 green; 8/8 Playwright**
- **Browser-runtime regression:** TypeScript typecheck + **297/297 Vitest across 17 files**
- **CodeRabbit:** one completed review; **4/4 actionable threads resolved**, 1 handoff nitpick addressed
- **Fixture decision:** `D-057` — **ACCEPTED**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Next gate:** explicit merge authorization for PR #12; do not begin T-604 automatically.

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

Key boundaries:

- private fixture under `examples/htmx-prep-list/`;
- Node 22 `node:http`, loopback-only `127.0.0.1:4173`;
- pinned `htmx.org@2.0.10` + Playwright Chromium;
- actual `packages/browser-runtime/src/**/*.ts` compiled into ignored `.tmp/runtime/` ESM;
- existing `prep_list.add_item@1` ActionDefinition reused;
- one business mutation route: `POST /items`;
- one test-only reset route: `POST /__test/reset`;
- human and production-driver paths converge on the same real HTMX request/server/swap behavior;
- stale replacement fails `binding_stale` with zero `/items` dispatch;
- body bounds, duplicate/missing/invalid/media-type input, wrong methods, traversal, and render escaping fail closed;
- fixture workflow uses `permissions: contents: read` and `persist-credentials: false`;
- production browser-runtime, Laravel, frozen spec, existing ActionDefinition, and `validate.yml` remain unchanged.

## Executable proof matrix

Eight real Chromium tests prove:

```text
1. HTMX 2.0.10 bootstrap + exact server-issued binding/source identity
2. human HTMX POST /items + swap + reload persistence
3. SurfaceRelay driver path + same-name form override + host-state preservation
4. normalized human/agent real-request convergence
5. page reload renews sourceId and bindingId together
6. equivalent real-DOM replacement makes old binding stale with zero /items request
7. invalid/duplicate/oversized/media-type/traversal requests fail closed
8. item content remains text in partial and full-page rendering
```

## Implementation / review evidence

```text
Design spec:                     docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
Implementation plan:             docs/superpowers/plans/2026-09-12-htmx-fixture.md
Plan checkpoint:                 678918d67c8ff33c09efdd2d2de196dab7cb6b4c / 34708675763 — 7/7 green

Bootstrap RED:                   a61e2899532706059fa76ee38ea781732257da9d
Bootstrap GREEN:                 a436bd2a9a4032877b45218a6cd6a35e801a1d07 / 34710621196
Human-path RED:                  6c11ada77b26fcfe0eddd5940e63f3cecf2af4f2
Human-path GREEN:                3a7e6de8777dad5b3889d390df55259bef2e60f6 / 34710965945
Driver-path RED:                 ae2dcd4dab234ef005dd94875a2cc1476e127bab
Driver-path GREEN:               e154743cfc8509ec22889cff49581c85ebbbb632 / 34717781774
Convergence/lifecycle:           28f79fc9dcd172254d4d3ecd3d16e1aa3c4e1d81 / 34717961616 — 5/5 green
Hardening RED:                   d0733055514ddef42907bbcd26018aa3bfef1f63 / 34718189031 — 6 passed / exactly 2 failed
Hardening GREEN:                 06a261636cd45fac2d95fc54228a2902f25c0bf1 + 71262bee0f5f011a87d2f1fb3216ebc528e9241e

Original verified fixture head:  31c0b85af56aaa06cac4efc2bced36bee0befcc2
Original validate:               34719068926 — 7/7 green
Original fixture:                34719068934 — 8/8 Playwright
Original review-prep:            0348bb6b50a6a8f36c4b3f1315515dd6e9213c87 / 34722974304 — 7/7 green
```

## External review — PR #12

CodeRabbit initially raised **4 actionable comments + 1 nitpick**.

### Workflow credential boundary — FIXED / CONFIRMED

Two comments represented the same security boundary. The fixture workflow now has:

```text
permissions: contents: read
actions/checkout@v4 -> persist-credentials: false
```

CodeRabbit explicitly confirmed both fixes and resolved both threads.

### Near-miss Content-Type — RED / GREEN FIXED / CONFIRMED

The original `startsWith()` check accepted `application/x-www-form-urlencoded-invalid` and mutated fixture state.

```text
RED commit:        da528e4db76af7c04a351c99a20df2e2da7387dd
RED fixture run:   34725182292 — 8 total / 7 passed / exactly 1 failed
RED failure:       expected 415, received 201
GREEN commit:      226f8169ee7cf251a079cd9f763e21791d49b39a
Review-fix head:   7284dd21292c17fc35ddfd3bfb045d50e3dab38c
Review-fix fixture:34725480861 — 8/8 green
Review-fix validate:34725480849 — 7/7 green
```

CodeRabbit explicitly confirmed the fix and resolved the thread.

### Review-prep readiness proof — CONFIRMED

The original review-prep head already had fresh `validate` plus a tracking-only diff proof. After review fixes, final reviewed head `04f98b9f...` received fresh validation again.

```text
Final reviewed head:      04f98b9f02c5012d396ece0641e358221ecd92c9
validate:                 34725996245 — 7/7 green
browser-runtime:          17 files / 297/297 + typecheck
htmx-fixture:             34725996254 — 8/8 green
7284dd21..04f98b9f diff:  STATUS.md / TASKS.md / REVIEW_REQUEST.md only
```

CodeRabbit independently verified this evidence and resolved the thread.

### Review handoff length — FIXED

`REVIEW_REQUEST.md` is a concise current-state handoff. Detailed history remains in `STATUS.md`, `TASKS.md`, the plan, and Git history.

## Decision state

- `D-057` — **ACCEPTED** for the verified real non-Laravel HTMX fixture proof.
- `D-054`, `D-055`, `D-056` remain accepted.
- `D-020` remains **PROPOSED**; T-604 shared conformance is still required.

## Current boundary

**T-603 is implemented, externally reviewed, and merge-ready in PR #12.** All four actionable CodeRabbit threads are resolved and the final reviewed head is green in both validation workflows. Merge remains pending explicit user authorization. Do not begin T-604 automatically.
