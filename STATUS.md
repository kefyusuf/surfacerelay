# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

A2-02 B2, app-owned browser descriptor and wiring — implementation prepared,
hosted evidence NOT RUN. The A2-02 read-only consumer's mounted Filament page
now issues the exact descriptor document (canonical definition JSON plus the
B1-accepted `LivewireBindingProducer` component binding). The app-owned glue
plus a fifteen-case Node suite pass locally against the published
`@surfacerelay/browser-runtime@0.1.0-alpha.3` root export: envelope return
semantics, fail-closed unsupported/mismatched/stale/expired/forged surfaces,
lease disposal and shared coordinator exclusion. PHP 8.4 syntax checked;
`validate.py` green. Hosted proof is pending on
[PR #54](https://github.com/kefyusuf/surfacerelay/pull/54); the real Chrome
`document.modelContext` proof remains A2-08. No write path, new driver,
runtime change or release is in scope.

## Next gate

Green hosted receipts on [PR #54](https://github.com/kefyusuf/surfacerelay/pull/54)
(`PASS_B2_DESCRIPTOR_EMISSION_ONLY`, `PASS_B2_BROWSER_WIRING_ONLY`, plus A2-02a
and B1 regressions). Do not start another task automatically. No further
registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2-02
consumer overlay and B1 test are tracked acceptance consumers, not runtime code.
