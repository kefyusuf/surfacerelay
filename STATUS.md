# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.2` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; see the [release record](docs/releases/0.1.0-alpha.2-publication.md).
npm alpha is alpha.2; latest is alpha.1. Install explicit versions.
Source metadata stays development-only; private preview guards remain NO-GO.
MCP/OpenAPI packages remain outside this release.

## Current delivery

T-910 implementation and local acceptance pass under accepted D-075.
Delivery and hosted checks: [PR #47](https://github.com/kefyusuf/surfacerelay/pull/47),
branch `feat/t-910-filament-selection-driver`. Consult the PR for live CI state.
Opt-in Filament driver validates exact owned binding data before selection writes,
shares component exclusion, and holds occupancy until the underlying call settles.
Normal Livewire behavior and server authority remain unchanged. New exports are
unreleased and absent from alpha.1/alpha.2.

Docker: 508 browser tests/typecheck/build/conformance, 16 real Filament browser
tests, clean artifact consumers, 30 signed HTTP/fault controls and canonical
validation pass. Real Chrome native/SQL controls pass, including selection drift,
current-record response loss and explicit replay. [Acceptance](docs/reviews/t910-filament-acceptance.md).
Independent reviews found no remaining blocker after the lossy snapshot correction.

## Needs decision

None for this task. No next development task is selected; do not begin one
automatically or publish another version.

## Limits and cleanup

Experimental prerelease: no stable API, production/payment, performance, SLA or
general native interoperability qualification. Unrelated UI/Livewire calls are
outside helper exclusion; deferred writes are not transactional. Native Completed
is not business success; unknown outcomes require state inspection before retry.
Task-owned Docker containers/demos/staging and native tabs are removed.
Existing images/resources and both user Composer locks remain. No worktree created.
