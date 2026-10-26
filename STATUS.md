# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

No implementation task selected. `main` is green: the Filament notification
drift was repaired by merged T-912 ([PR #55](https://github.com/kefyusuf/surfacerelay/pull/55),
test-only, full `php-tests` matrix green again), and A2-02 B2 — app-owned
browser descriptor and wiring — was merged via
[PR #54](https://github.com/kefyusuf/surfacerelay/pull/54) with hosted
receipts `PASS_B2_DESCRIPTOR_EMISSION_ONLY` 3/3 and `PASS_B2_BROWSER_WIRING_ONLY`
15/15 plus A2-02a 12/12 and B1 2/2 regressions on a registry-installed
consumer. The mounted Filament page issues the exact descriptor document;
the glue consumes only the published alpha.3 root export.

## Next gate

Owner selects the next task explicitly: A2-08 (real Chrome
`document.modelContext` discovery/invocation proof) or the minimal
quickstart work. No further registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2-02
consumer overlay and B1 test are tracked acceptance consumers, not runtime code.
