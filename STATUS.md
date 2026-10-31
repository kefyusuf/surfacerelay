# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-921 — repository refresh (selected; PR pending). T-920's QA follow-ups are
merged ([PR #64](https://github.com/kefyusuf/surfacerelay/pull/64)). This task
brings the entry-point documents up to date with everything merged since
alpha.3: README's acceptance status (A2-08 native proof), reading order and
guide links, and the CHANGELOG Unreleased section covering T-912…T-920. Docs
only; no runtime code changes.

## Next gate

T-921 PR; after the merge the repository-side queue is empty by design — next
task selection comes from the three signal sources (the parked outreach send,
consumer-feedback reports, monthly momentum watch entries) or an owner
decision.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
