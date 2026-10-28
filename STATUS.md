# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-915 — first-contact drafts and outreach ledger (selected; implementation
starting). T-914's kit is merged ([PR #58](https://github.com/kefyusuf/surfacerelay/pull/58)):
the honest ask, the feedback instrument, the `consumer-feedback` issue
template and the dated candidate research. This task makes the outreach
send-ready: personalized drafts grounded in live-verified candidate state
(liberu-ecommerce pushed 2026-10-27 on Filament ^5.11 — our exact verified
stack; tomatophp active on ^5.0) and a contact ledger. Sending stays
owner-authorized, one candidate at a time. Docs only; no code changes.

## Next gate

T-915 PR; after that the outreach is blocked on one thing only — the owner
sending the first message — and the first unassisted external quickstart
completion becomes the success signal.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
