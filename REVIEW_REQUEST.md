# Review Request — native WebMCP proof

Branch `feat/webmcp-live-browser-proof`, stacked on `docs/t-804-release-facing-documentation` (#24).

## What changed

- `resolveDocumentModelContext()` added to the browser runtime root API (feature
  detection only; no polyfill, no `navigator` fallback).
- HTMX fixture registers its Action tool on Chromium's native `document.modelContext`;
  new `chromium-webmcp` Playwright project drives it through `executeTool()`.
- Tracking files reduced to current state; prior versions moved to `docs/archive/`.

## Review focus

- Does the live proof really cross the browser's WebMCP boundary (no direct driver call)?
- Are the stated limits honest: one flag-enabled Chromium build, page as caller, no real agent?
- Is the T-806 gap (422 reported as success) correctly scoped as a contract change?

## Verification

- `packages/browser-runtime`: `npm test` 338/338, `npx tsc --noEmit` clean.
- `examples/htmx-prep-list`: `npm test` 15/15 (8 existing + 7 WebMCP, one an expected `test.fail()`).
- `python scripts/validate.py`, `scripts/check_release_guardrails.py`: pass.
- Python tooling: release-candidate 49/49, browser release-candidate 25/25;
  conformance 46/47 — the one error (Windows temp-dir lock) also fails on the
  unchanged baseline locally and is not touched here.
