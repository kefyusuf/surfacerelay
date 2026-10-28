# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-917 — outreach intake readiness (selected; implementation on branch).
T-916's decision guide is merged ([PR #60](https://github.com/kefyusuf/surfacerelay/pull/60))
and linked from POSITIONING and the quickstart. This task closes the intake
side before any outreach goes out: [REPORT-INTAKE.md](docs/consumers/outreach/REPORT-INTAKE.md)
(triage categories, one-report-vs-two evidence rules, running metrics,
reporter loop-closure) and a docs link-integrity checker
([scripts/check_docs_links.py](scripts/check_docs_links.py)) — 255 links
verified, six broken root-absolute-style pointers fixed, the check added to
the per-candidate send checklist. Docs and one standalone script; no runtime
code changes.

## Next gate

T-917 PR; after that the repository-side queue is intentionally empty — the
loop waits on external signals (the owner's first outreach send and filed
consumer-feedback reports) before any new feature work.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
