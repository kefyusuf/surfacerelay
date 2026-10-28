# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-916 — consumer-facing decision guide (selected; implementation starting).
T-915's send-ready drafts are merged
([PR #59](https://github.com/kefyusuf/surfacerelay/pull/59)): the outreach
phase is now blocked on one owner-authorized action — sending Draft 1 to the
live-verified liberu candidate — while responses arrive in parallel. This
task sharpens positioning where it affects adoption: the honest
"when SurfaceRelay, when a plain MCP tool" decision guide
([when-to-use.md](docs/consumers/when-to-use.md)), linked from the quickstart
and POSITIONING. Docs only; no code changes.

## Next gate

T-916 PR; after that the repository-side queue is intentionally empty — the
loop waits on external signals (the owner's first outreach send and filed
consumer-feedback reports) before any new feature work.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
