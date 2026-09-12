# External Review Request — T-603 Non-Laravel HTMX Fixture

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-603 — Non-Laravel HTMX fixture app`
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof`
- **Branch:** `feat/htmx-fixture`
- **Base:** `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- **Original verified fixture head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Review-fix executable head:** `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`
- **Review-fix validate:** `34725480849` — **7/7 green**
- **Review-fix real fixture:** `34725480861` — **1/1 green / 8/8 Playwright**
- **Browser-runtime regression:** **17 files / 297/297 + TypeScript typecheck**
- **Decision:** `D-057` — **ACCEPTED**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Merge:** pending explicit authorization

## What to review

T-603 adds a real non-Laravel fixture proving the already-merged T-601/T-602 HTMX path in Chromium:

```text
prep_list.add_item@1
  -> server-issued page RuntimeBinding
  -> real htmx.org 2.0.10
  -> human click OR production HtmxBrowserDriver.execute()
  -> POST /items
  -> same server mutation
  -> same HTML fragment
  -> same hx-target / hx-swap
```

Please focus on:

1. production `HtmxBrowserDriver` is actually exercised; no duplicate agent mutation path exists;
2. human and agent requests converge on the same `/items` HTMX route;
3. page-scoped `sourceId` / `bindingId` renewal and stale no-retarget behavior are truthful;
4. `POST /__test/reset` and the browser bridge remain test-only controls;
5. request/body/media-type/method validation, traversal safety, and render escaping fail closed;
6. fixture dependencies/workflow remain isolated from production browser-runtime and Laravel packages;
7. T-603 does not claim T-604 shared conformance or promote `D-020`.

## Current review findings

CodeRabbit completed one review and initially raised **4 actionable comments + 1 nitpick**.

Addressed executable findings:

- workflow token restricted to `contents: read`;
- `actions/checkout@v4` uses `persist-credentials: false`;
- near-miss `application/x-www-form-urlencoded-invalid` is rejected with `415`;
- near-miss bug reproduced before the fix: `da528e4db76af7c04a351c99a20df2e2da7387dd`, fixture run `34725182292` — **7 passed / exactly 1 failed**, expected `415`, received `201`;
- GREEN review-fix head: `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`, fixture `34725480861` — **8/8 green**, validate `34725480849` — **7/7 green**.

Tracking/readiness finding is covered by the recorded original review-prep proof:

```text
0348bb6b50a6a8f36c4b3f1315515dd6e9213c87
validate 34722974304 — 7/7 green
31c0b85a..0348bb6b diff — tracking-only
```

Detailed implementation/TDD history remains in `STATUS.md` and `TASKS.md` rather than this handoff.

## Expected change surface

```text
.github/workflows/htmx-fixture.yml
examples/htmx-prep-list/**
docs/superpowers/specs/2026-09-12-htmx-fixture-design.md
docs/superpowers/plans/2026-09-12-htmx-fixture.md
docs/DECISION-REGISTER.md
STATUS.md
TASKS.md
REVIEW_REQUEST.md
```

Explicitly unchanged:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package*.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

## Current boundary

T-603 is implemented and under external review in PR #12. The executable review-fix head is green. After this tracking-only closure commit, require a fresh normal `validate` run and a diff proof showing only tracking documents changed since `7284dd21...`. Then close the review threads and wait for explicit merge authorization. Do not begin T-604 automatically.
