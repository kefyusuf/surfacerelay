# Review Request — native WebMCP proof + T-806

Branch `feat/webmcp-live-browser-proof`, stacked on `docs/t-804-release-facing-documentation` (#24).

## What changed

- `resolveDocumentModelContext()` added to the browser runtime root API (feature
  detection only; no polyfill, no `navigator` fallback).
- HTMX fixture registers its Action tool on Chromium's native `document.modelContext`;
  new `chromium-webmcp` Playwright project drives it through `executeTool()`.
- **T-806:** `GlobalHtmxBrowserRuntime.ajax()` resolves only when the request it issued
  (matched by the xhr in its own `htmx:beforeSend`) completed with
  `successful === true`. New driver-local codes `htmx_request_not_sent` and
  `htmx_request_failed`. Proposed as D-074.
- Tracking files reduced to current state; prior versions moved to `docs/archive/`.

## Review focus

- D-074 correlation: can a foreign request on the same source be mistaken for ours?
  (Busy sources already fail closed per D-055.)
- `htmx:onLoadError` path: htmx's promise never settles there; the runtime now rejects.
- Interface contract change for custom `HtmxBrowserRuntime` implementations and the new
  `addEventListener` requirement on sources (fails closed as `htmx_runtime_unsupported`).
- Is keeping "no business output" (D-054) right until T-808 decides a convention?

## Verification

- `packages/browser-runtime`: `npm test` 355/355, `npx tsc --noEmit` clean; RED proven
  first (14 new runtime tests failing, one by hanging).
- `examples/htmx-prep-list`: `npm test` 17/17 on real HTMX 2.0.10 + Chromium 153,
  including 422 and vetoed-request cases through `executeTool()`.
- `scripts/run_conformance.py`: 7 PASS / 1 NOT_APPLICABLE (unchanged).
- `python scripts/validate.py`, `scripts/check_release_guardrails.py`: pass.
- Python tooling: release-candidate 49/49, browser release-candidate 25/25;
  conformance 46/47 — the one error (Windows temp-dir lock) also fails on the
  unchanged baseline locally.

