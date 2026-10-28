# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-914 — first external consumer outreach (selected; implementation starting).
`main` is green and the delivery chain a new adopter touches is complete and
CI-verified: the minimal quickstart ([PR #57](https://github.com/kefyusuf/surfacerelay/pull/57),
`PASS_T913_QUICKSTART_READ_ONLY` 5/5 + `PASS_T913_GLUE_NODE_ONLY` 3/3), the
browser wiring (B2, PR #54) and the real Chrome native proof (A2-08, PR #56).
This task ships the honest ask — outreach text, structured feedback form, a
consumer-feedback issue template and a researched candidate list — and then
measures one unassisted external quickstart completion. No code changes.

## Next gate

T-914 PR (outreach kit + template + researched targets); after that, actual
external outreach is an owner-authorized activity — a maintainer completes
the quickstart unassisted, and their report becomes a decision-record input.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
