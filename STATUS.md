# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-911 publication and actual registry acceptance pass. Preparation
[PR #48](https://github.com/kefyusuf/surfacerelay/pull/48) and frozen-source CI passed.
Frozen source/tag is `83ebb86984ef99ad397fa18ccaf48dbf5f8150e5`; the mirror/tag and
Packagist reference match `1d20fcc98f6a525362e30138aff44d23b719dde1`.
[Publication receipt](docs/reviews/alpha-0.1.0-alpha.3-publication.json) records
registry metadata, immutable provenance and all 205 file hashes.

Actual npm consumer import/types/bundle/smoke/deep-import controls pass. Actual
Packagist 11 HTTP/race tests and authorization mutation pass. Installed Filament
45 browser/160 Laravel files match; 23 HTTP + 7 fault controls and nine Chrome
native/SQL cases pass. Canonical validation and independent receipt review pass.
Publication receipt delivery is on `docs/t-911-alpha3-publication-receipt`;
hosted checks remain to be observed.

## Next gate

Review and merge the documentation receipt after green checks. Do not start a
new implementation task automatically. No further registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
The owned registry container/native tabs and `.tmp/t911-session` are removed.
Publication delivery helpers are removed at task completion. Existing images/resources and
both user Composer locks are kept. No new image, named volume or worktree was created.
