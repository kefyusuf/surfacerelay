# HTMX Prep List Fixture

This directory is the executable T-603 portability proof for SurfaceRelay's HTMX binding. It is intentionally a small non-Laravel fixture, not a production starter application.

## What it proves

The fixture runs a plain Node.js 22 `node:http` server on `127.0.0.1:4173`, loads the pinned `htmx.org@2.0.10` browser runtime, compiles the real SurfaceRelay browser-runtime TypeScript source into temporary ESM, and drives the page with Playwright Chromium.

It reuses the existing `../prep-list/action.add-item.json` definition for `prep_list.add_item@1`.

There is exactly one business mutation route:

```text
POST /items
```

Both execution paths converge on that route and the same rendered HTMX source behavior:

```text
Human:
button click -> real HTMX -> POST /items -> server mutation -> HTML fragment -> hx-target/hx-swap

SurfaceRelay:
HtmxBrowserDriver.execute() -> host HTMX -> POST /items -> same server mutation -> same fragment -> same hx-target/hx-swap
```

The fixture also proves:

- real `HX-Request: true` network traffic for both paths;
- SurfaceRelay Action input overrides a stale same-named form value while ordinary hidden host state remains present;
- full-page reload preserves server-side item state and renews page-scoped `sourceId` / `bindingId`;
- replacing the exact DOM source with an equivalent new source causes the old binding to fail `binding_stale` without sending `POST /items`;
- malformed, duplicate-name, oversized, and unsupported mutation requests fail closed;
- runtime static serving rejects nested/traversal paths;
- item content is HTML-escaped in partial and full-page rendering.

## Test-only surface

The only test-only server endpoint is:

```text
POST /__test/reset
```

It only clears in-memory fixture state. It is not a second business mutation path.

The browser test bridge exposes only delegation to the production `HtmxBrowserDriver.execute()` path plus a DOM-only source-replacement helper. It does not call `fetch`, XHR, or `htmx.ajax()` directly and does not mutate server state.

## Run locally

```bash
cd examples/htmx-prep-list
npm ci
npx playwright install chromium
npm test
```

The fixed port is `4173`. Startup fails if another process already owns that port; the fixture never silently chooses a different port or reuses an existing server.

Generated runtime JavaScript and Playwright outputs live under `.tmp/` and are not committed.

## CI

`.github/workflows/htmx-fixture.yml` runs this real-browser proof only when fixture/browser-runtime inputs change. The normal repository `validate` workflow remains separate.

## Decision boundary

T-603 can establish the real HTMX fixture proof captured by D-057. It does **not** establish shared Livewire/HTMX conformance. D-020 remains `PROPOSED` until T-604 is completed.
