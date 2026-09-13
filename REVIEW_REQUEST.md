# External Review Record — T-603 Non-Laravel HTMX Fixture

## Review state

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-603 — Non-Laravel HTMX fixture app`
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof`
- **Branch:** `feat/htmx-fixture`
- **Base:** `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- **Original verified fixture head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Review-fix executable head:** `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`
- **Final reviewed head before closure record:** `04f98b9f02c5012d396ece0641e358221ecd92c9`
- **Final reviewed validate:** `34725996245` — **7/7 green**
- **Final reviewed fixture:** `34725996254` — **8/8 real Chromium tests**
- **Browser-runtime regression:** **17 files / 297/297 + TypeScript typecheck**
- **CodeRabbit:** **4/4 actionable threads resolved; 0 unresolved**
- **Handoff nitpick:** addressed by keeping this record concise
- **Decision:** `D-057` — **ACCEPTED**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**
- **Merge:** ready, pending explicit user authorization

## Reviewed boundary

T-603 proves the already-merged T-601/T-602 HTMX path in a real non-Laravel browser/server fixture:

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

Review confirmed:

- production `HtmxBrowserDriver` is exercised; there is no duplicate agent mutation route;
- human and agent converge on the same `/items` HTMX path;
- page-scoped identities renew on reload and equivalent replacement does not inherit an old binding;
- test reset/bridge helpers remain outside the business mutation path;
- request/body/media-type/method validation, traversal protection, and render escaping fail closed;
- fixture CI has read-only token permissions and does not persist checkout credentials;
- fixture dependencies stay isolated from production browser-runtime/Laravel packages;
- T-603 does not claim T-604 shared conformance and does not accept `D-020`.

## CodeRabbit closure

One CodeRabbit review initially raised **4 actionable comments + 1 nitpick**.

### Workflow credential hardening — resolved

```text
permissions: contents: read
checkout: persist-credentials: false
```

CodeRabbit explicitly rechecked and resolved both workflow threads.

### Near-miss form media type — resolved with RED/GREEN proof

```text
RED:        da528e4db76af7c04a351c99a20df2e2da7387dd
RED run:    34725182292 — 8 total / 7 passed / exactly 1 failed
Failure:    expected 415, received 201
GREEN:      226f8169ee7cf251a079cd9f763e21791d49b39a
```

Final server behavior accepts the exact `application/x-www-form-urlencoded` media type (parameters allowed) and rejects near-miss types. CodeRabbit confirmed and resolved the thread.

### Review-prep readiness — resolved

```text
Original review-prep: 0348bb6b50a6a8f36c4b3f1315515dd6e9213c87
validate:             34722974304 — 7/7 green
review-prep diff:     tracking-only

Final reviewed head:  04f98b9f02c5012d396ece0641e358221ecd92c9
validate:             34725996245 — 7/7 green
fixture:              34725996254 — 8/8 green
browser-runtime:      297/297 + typecheck
closure diff:         7284dd21..04f98b9f — STATUS.md / TASKS.md / REVIEW_REQUEST.md only
```

CodeRabbit independently verified the evidence and resolved the thread.

## Scope

Changed runtime/example surface is limited to:

```text
.github/workflows/htmx-fixture.yml
examples/htmx-prep-list/**
```

plus design/tracking documentation.

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

T-603 is **implemented, externally reviewed, and merge-ready**. All actionable review threads are resolved. Merge is the next explicit gate. Do not begin T-604 automatically; `D-020` remains PROPOSED.
