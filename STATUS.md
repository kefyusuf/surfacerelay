# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-922 — one-command try-it flow for the quickstart (selected; PR pending).
Ships a committed toolchain Dockerfile, a host wrapper
([try-it.sh](examples/minimal-quickstart/try-it.sh)) that stands up a
disposable registry consumer on `http://127.0.0.1:8080` with a printed Chrome
checklist and a self-diagnosing port-forward warning, a demo seeder, and
getting-started/README updates. Verified end to end on this machine
(setup, seed, serve, endpoints, PHPUnit 5/5 inside the demo container). Demo
tooling only; no runtime changes.

## Next gate

T-922 PR merge; after that the repository-side queue is empty by design —
next task selection comes from the three signal sources (the parked outreach
send, consumer-feedback reports, monthly momentum watch entries) or an owner
decision.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
