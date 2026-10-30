# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

No implementation task selected. The repository-side queue is intentionally
empty and everything a new adopter touches is merged with green checks: the
A2 series (server read, binding production, browser wiring B2, real Chrome
native proof A2-08), the minimal quickstart (T-913), the outreach kit and
send-ready drafts (T-914/T-915), the when-to-use decision guide (T-916), the
report intake process plus docs link checker (T-917), and the WebMCP momentum
watch (T-918). A local verification sweep on this machine additionally ran
the full Python suite (195 tests, one pre-existing Windows skip now fixed by
T-919) and the browser-runtime vitest suite (508/508).

## Next gate

New task selection comes from three signal sources only: (1) the owner's
first outreach send and any `consumer-feedback` reports, (2) monthly entries
in the [WebMCP momentum watch](docs/plans/2026-10-28-webmcp-momentum-watch.md),
(3) an owner decision. Do not start feature work without one of these.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
