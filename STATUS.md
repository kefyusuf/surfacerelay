# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.2` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; see the [release record](docs/releases/0.1.0-alpha.2-publication.md).
npm alpha is alpha.2; latest is alpha.1. Install explicit versions.
Source metadata stays development-only; private preview guards remain NO-GO.
MCP/OpenAPI packages remain outside this release.

## Current delivery

T-910 is merged through [PR #47](https://github.com/kefyusuf/surfacerelay/pull/47);
main CI passed. T-911 coordinated alpha.3 preparation is active on
`feat/t-911-alpha3-preparation`; see the [approved plan](docs/releases/0.1.0-alpha.3.md).
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

None for T-911 scope. Preparation Docker 195 Python/508 browser tests, canonical
validation, separate public tarball consumer and Laravel 11 HTTP/races pass;
[evidence](docs/reviews/t911-alpha3-preparation.md) labels working-tree provenance.
Exact merged-source/native acceptance and publication remain pending gates.

## Limits and cleanup

Experimental prerelease: no stable API, production/payment, performance, SLA or
general native interoperability qualification. Unrelated UI/Livewire calls are
outside helper exclusion; deferred writes are not transactional. Native Completed
is not business success; unknown outcomes require state inspection before retry.
T-910 resources were removed. T-911 owns `surfacerelay-t911-preparation` and
`.tmp/t911-session`; clean them when verification ends.
Existing images/resources and both user Composer locks remain. No worktree created.
