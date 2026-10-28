# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-918 — WebMCP momentum watch (Faz 5; selected, implementation on branch).
T-917's intake readiness is merged ([PR #61](https://github.com/kefyusuf/surfacerelay/pull/61))
plus one missed link repair completed on `main` (0c69288; 260/260 links
resolve). The outreach phase stays blocked on the owner sending
[Draft 1](docs/consumers/outreach/DRAFTS.md). This task converts the
remaining conditional bet (Faz 5) into a dated monitoring note: the WebMCP
signals to watch, the pre-agreed pivot per outcome, and an append-only log.
Monitoring only; no code changes.

## Next gate

T-918 PR; then the repository-side queue is empty by design — external
signals (the first outreach send, filed consumer-feedback reports, WebMCP
momentum entries) drive everything that follows.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
