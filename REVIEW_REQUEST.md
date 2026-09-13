# External Review / Merge Record — T-603 Non-Laravel HTMX Fixture

## Final status

- **Repository:** `github.com/kefyusuf/surfacerelay`
- **Task:** `T-603 — Non-Laravel HTMX fixture app`
- **Pull request:** `#12 — test(htmx): add real non-Laravel fixture proof` — **MERGED**
- **Base entering T-603:** `main@83df122d84f6881a4a3541bd5c72e1108e5d1e48`
- **Original verified fixture head:** `31c0b85af56aaa06cac4efc2bced36bee0befcc2`
- **Review-fix executable head:** `7284dd21292c17fc35ddfd3bfb045d50e3dab38c`
- **Final feature/review-closure head:** `076108554d6995fea65108ca07c9b64d3994d459`
- **Final feature validate:** `34727726895` — **7/7 green**
- **Final feature fixture:** `34727726888` — **8/8 real Chromium tests**
- **Merge commit:** `e98c919b90f9f19b58ae56b88e391f1abbb179e7`
- **Post-merge main validate:** `34758253025` — **7/7 green**
- **Post-merge main fixture:** `34758253003` — **8/8 real Chromium tests**
- **Browser on post-merge main:** **17 files / 297/297 Vitest + TypeScript typecheck**
- **CodeRabbit:** **4/4 actionable threads resolved; 0 unresolved**, 1 handoff nitpick addressed
- **Decision:** `D-057` — **ACCEPTED**
- **Portability:** `D-020` — **PROPOSED; remains gated on T-604**
- **T-604:** **NOT STARTED**

## Reviewed behavior

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

The review and executable proofs confirmed:

- production `HtmxBrowserDriver` is exercised; there is no duplicate agent mutation route;
- human and agent converge on the same `/items` HTMX path;
- page-scoped `sourceId` / `bindingId` renew on reload;
- an equivalent rendered replacement never inherits the old binding; stale execution dispatches zero `/items` requests;
- `POST /__test/reset` and the browser bridge remain test-only controls;
- Action input overrides stale same-name form state while ordinary host request state remains present;
- request/body/media-type/method validation, traversal protection, and render escaping fail closed;
- fixture CI uses read-only `contents` permission and does not persist checkout credentials;
- fixture dependencies stay isolated from production browser-runtime/Laravel packages;
- T-603 does not claim T-604 shared conformance and does not promote `D-020`.

## CodeRabbit closure

CodeRabbit initially raised **4 actionable comments + 1 nitpick**.

### Workflow credential hardening — FIXED / CONFIRMED

```text
permissions: contents: read
checkout: persist-credentials: false
```

Both workflow-security threads were explicitly rechecked and resolved.

### Near-miss form media type — RED / GREEN FIXED / CONFIRMED

```text
RED:        da528e4db76af7c04a351c99a20df2e2da7387dd
RED run:    34725182292 — 8 total / 7 passed / exactly 1 failed
Failure:    expected 415, received 201
GREEN:      226f8169ee7cf251a079cd9f763e21791d49b39a
```

The final server parses the media type token and accepts only exact `application/x-www-form-urlencoded` while allowing normal parameters. CodeRabbit explicitly confirmed the fix and resolved the thread.

### Review-prep readiness / handoff — CONFIRMED

The original review-prep and later review-closure heads were both independently validated. The detailed historical timeline remains in `STATUS.md` and `TASKS.md`; this file remains a concise handoff/closure record.

## Verification evidence

```text
Original verified fixture head:  31c0b85af56aaa06cac4efc2bced36bee0befcc2
Original validate:               34719068926 — 7/7 green
Original fixture:                34719068934 — 8/8 Playwright

Review-fix executable head:      7284dd21292c17fc35ddfd3bfb045d50e3dab38c
Review-fix validate:             34725480849 — 7/7 green
Review-fix fixture:              34725480861 — 8/8 Playwright

Final feature/review head:       076108554d6995fea65108ca07c9b64d3994d459
Final feature validate:          34727726895 — 7/7 green
Final feature fixture:           34727726888 — 8/8 Playwright
Final feature browser:           297/297 + typecheck
Review threads:                  4/4 actionable resolved / 0 unresolved

Merge commit:                    e98c919b90f9f19b58ae56b88e391f1abbb179e7
Post-merge main validate:        34758253025 — 7/7 green
Post-merge main fixture:         34758253003 — 8/8 Playwright
Post-merge browser:              297/297 + typecheck
```

## Scope

Changed runtime/example surface is limited to:

```text
.github/workflows/htmx-fixture.yml
examples/htmx-prep-list/**
```

plus design/tracking documentation.

Explicitly unchanged by T-603:

```text
packages/browser-runtime/src/**
packages/browser-runtime/package*.json
packages/laravel/**
spec/0.1/**
examples/prep-list/action.add-item.json
.github/workflows/validate.yml
```

## Boundary after merge

T-603 is **DONE / REVIEWED / MERGED / MAIN REVALIDATED**.

`D-057` is accepted only for the verified real HTMX fixture boundary. `D-020` remains PROPOSED. T-604 has not started; beginning its shared-conformance design requires a new explicit user gate.
