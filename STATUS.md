# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

No implementation task selected. `main` is current and the try-it flow is
merged ([PR #66](https://github.com/kefyusuf/surfacerelay/pull/66),
T-922): one command
(`bash examples/minimal-quickstart/try-it.sh`) stands up a disposable
registry consumer with the documented quickstart code, seeds demo data and
serves it with a printed Chrome checklist. The owner has parked the first
outreach send (Draft 1) for later.

## Next gate

The repository-side queue is empty by design — new task selection comes from
the three signal sources (the parked outreach send, consumer-feedback
reports, monthly momentum watch entries) or an owner decision.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
