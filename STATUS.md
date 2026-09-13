# Project Status

## Snapshot

- **Project:** SurfaceRelay
- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Branch:** `main`
- **Milestone:** `M6 — HTMX Portability Proof` — **IN_PROGRESS**
- **Last completed task:** `T-603 — Non-Laravel HTMX fixture app`
- **T-603 state:** **DONE / REVIEWED / MERGED / MAIN REVALIDATED**
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof` — **MERGED**
- **Final feature/review-closure head:** `076108554d6995fea65108ca07c9b64d3994d459`
- **Final feature validate:** `34727726895` — **7/7 green**
- **Final feature real fixture:** `34727726888` — **1/1 green; 8/8 Playwright**
- **Merge commit:** `e98c919b90f9f19b58ae56b88e391f1abbb179e7`
- **Post-merge main validate:** `34758253025` — **7/7 green**
- **Post-merge main fixture:** `34758253003` — **1/1 green; 8/8 Playwright**
- **Browser on post-merge main:** TypeScript typecheck + **297/297 Vitest across 17 files**
- **CodeRabbit:** one completed review; **4/4 actionable threads resolved**, 1 handoff nitpick addressed
- **Fixture decision:** `D-057` — **ACCEPTED**
- **Portability decision:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Next gate:** explicit authorization to begin T-604 design; do not start it automatically.

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

Key verified boundaries:

- private fixture under `examples/htmx-prep-list/`;
- Node 22 `node:http`, loopback-only `127.0.0.1:4173`;
- pinned `htmx.org@2.0.10` + Playwright Chromium;
- actual `packages/browser-runtime/src/**/*.ts` compiled into ignored `.tmp/runtime/` ESM;
- existing `prep_list.add_item@1` ActionDefinition reused;
- exactly one business mutation route: `POST /items`;
- exactly one test-only reset route: `POST /__test/reset`;
- human and production-driver paths converge on the same real HTMX request/server/swap behavior;
- explicit SurfaceRelay Action input overrides stale same-name form state while ordinary host state is preserved;
- reload renews page-scoped `sourceId` + `bindingId`;
- equivalent rendered replacement cannot inherit the old binding; stale execution sends zero `/items` requests;
- body bounds, duplicate/missing/invalid/media-type input, wrong methods, traversal, and rendering escaping fail closed;
- fixture CI uses `permissions: contents: read` and `persist-credentials: false`;
- production browser-runtime, Laravel, frozen spec, existing ActionDefinition, and `.github/workflows/validate.yml` remain unchanged.

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

## Implementation and review evidence

```text
Design spec:                       docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
Implementation plan:               docs/superpowers/plans/2026-09-12-htmx-fixture.md
Plan checkpoint:                   678918d67c8ff33c09efdd2d2de196dab7cb6b4c / 34708675763 — 7/7 green
Original verified fixture head:    31c0b85af56aaa06cac4efc2bced36bee0befcc2
Original validate:                 34719068926 — 7/7 green
Original fixture:                  34719068934 — 8/8 Playwright
Original review-prep:              0348bb6b50a6a8f36c4b3f1315515dd6e9213c87 / 34722974304 — 7/7 green

CodeRabbit media-type RED:         da528e4db76af7c04a351c99a20df2e2da7387dd
RED fixture run:                   34725182292 — 8 total / 7 passed / exactly 1 failed
RED failure:                       expected 415, received 201
Media-type GREEN:                  226f8169ee7cf251a079cd9f763e21791d49b39a
Workflow security hardening:       7284dd21292c17fc35ddfd3bfb045d50e3dab38c
Review-fix validate:               34725480849 — 7/7 green
Review-fix fixture:                34725480861 — 8/8 Playwright

Final reviewed head:               076108554d6995fea65108ca07c9b64d3994d459
Final reviewed validate:           34727726895 — 7/7 green
Final reviewed fixture:            34727726888 — 8/8 Playwright
Final reviewed browser:            17 files / 297/297 + typecheck
CodeRabbit threads:                4/4 actionable resolved / 0 unresolved
Review-closure diff from 7284dd21: STATUS.md / TASKS.md / REVIEW_REQUEST.md only

Merge commit:                       e98c919b90f9f19b58ae56b88e391f1abbb179e7
Post-merge validate:                34758253025 — 7/7 green
Post-merge fixture:                 34758253003 — 8/8 Playwright
Post-merge browser:                 17 files / 297/297 + typecheck
```

## External review closure

CodeRabbit initially raised **4 actionable comments + 1 nitpick**.

- Workflow token scope was restricted to `contents: read`.
- `actions/checkout@v4` now uses `persist-credentials: false` before npm-controlled execution.
- A near-miss `application/x-www-form-urlencoded-invalid` request was reproduced as a RED regression and fixed with exact media-type parsing.
- Review-prep and closure-head verification evidence was recorded and independently confirmed.
- `REVIEW_REQUEST.md` was reduced to a concise handoff while detailed history remains here and in `TASKS.md`.
- All four actionable CodeRabbit threads were explicitly confirmed and resolved.

## Decision state

- `D-057` — **ACCEPTED** for the verified real non-Laravel HTMX fixture proof.
- `D-054`, `D-055`, `D-056` remain accepted for their T-602 boundaries.
- `D-020` remains **PROPOSED**. T-603 is real portability evidence, but shared Livewire/HTMX conformance is still a T-604 gate.

## Current boundary

**T-603 is closed: DONE / REVIEWED / MERGED / MAIN REVALIDATED.**

T-604 has **not started**. Do not infer shared cross-driver conformance or promote `D-020` from T-603. The next allowed action is explicit authorization to begin the T-604 design gate.
