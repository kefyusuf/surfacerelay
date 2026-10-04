# Review Request — T-808 HTMX business output

Branch `feat/t-808-htmx-result`, top of the open stack (#23 → … → #31).

## What changed

- `GlobalHtmxBrowserRuntime.ajax()` resolves with the plain object the server declared as
  `surfacerelay:result` in the issued request's `HX-Trigger` JSON header; otherwise
  `undefined`. `HtmxBrowserRuntime.ajax()` now returns `Promise<unknown>`.
- Read from the correlated xhr, not htmx's event detail (htmx adds `elt` to it).
- Absent, non-JSON, array, non-object or null declarations → `undefined`, never an error:
  the server already applied the request, and an error would invite a duplicate retry.
- HTMX fixture `POST /items` declares `{itemId}`; the live WebMCP tool call returns it.
- Proposed **D-078**; open question resolved.

## Review focus

- Is "malformed declaration → `undefined`" the right trade-off vs. surfacing an app bug?
- No browser-side output-schema validation (no JSON Schema dependency); output policy
  stays server-side.

## Verification

- RED first: unit positive case and the live `executeTool()` result (`"undefined"`).
- `packages/browser-runtime`: `npm test` 364/364, `tsc --noEmit`.
- `examples/htmx-prep-list`: 17/17 (real HTMX 2.0.10, Chromium 153).
- `run_conformance.py` 7 PASS / 1 N/A; `validate.py`; `test_browser_release_candidate` OK.
