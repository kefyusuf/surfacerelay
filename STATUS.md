# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-928 delivers the CI hygiene item of the owner-grouped norms backlog:
composer/pip caches, `concurrency` groups with cancel-in-progress on all
nine workflows, `timeout-minutes` on every job, and a monthly
`htmx-fixture` schedule. Delivered on `chore/t928-ci-hygiene` (handoff:
[REVIEW_REQUEST.md](REVIEW_REQUEST.md)); T-923…T-927 are merged and current
([PRs #67](https://github.com/kefyusuf/surfacerelay/pull/67)–
[#71](https://github.com/kefyusuf/surfacerelay/pull/71)). The owner has
parked the first outreach send (Draft 1) for later.

## Next gate

The repository-side queue is empty by design — new task selection comes from
the signal sources (the parked outreach send, consumer-feedback reports,
monthly momentum watch entries) or an owner decision. Of the review note's
two parked decisions, the repo description/topics were applied with owner
approval and the npm `latest` dist-tag stays at alpha.1; the review backlog
holds further parked candidates, none selected. The filament-live drift
standing item is resolved by T-925 (pending merge); the workflow now also
surfaces dependency drift on a monthly schedule.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
