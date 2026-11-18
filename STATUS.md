# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

No implementation task selected. `main` is current: T-924 is merged
([PR #68](https://github.com/kefyusuf/surfacerelay/pull/68)) — PHPStan level
5 on `packages/laravel` with a committed 23-entry baseline and a CI job that
fails on findings outside it — alongside T-923
([PR #67](https://github.com/kefyusuf/surfacerelay/pull/67)) and the try-it
flow (T-922, [PR #66](https://github.com/kefyusuf/surfacerelay/pull/66)).
The owner has parked the first outreach send (Draft 1) for later.

## Next gate

The repository-side queue is empty by design — new task selection comes from
the signal sources (the parked outreach send, consumer-feedback reports,
monthly momentum watch entries) or an owner decision. Of the review note's
two parked decisions, the repo description/topics were applied with owner
approval and the npm `latest` dist-tag stays at alpha.1; the review backlog
holds further parked candidates, none selected. One standing item: the
`filament-live` Playwright job is red on `main` since 2026-11-17 — 9/16
tests fail on the approval modal because the example resolves Composer deps
fresh with no lock, so Filament/Livewire drift accumulated while the
path-gated job never ran (last green 2026-10-08); the `validate` suite,
including the T-924 phpstan job, is green. Repair is a candidate task
awaiting owner selection.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
