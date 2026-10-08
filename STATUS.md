# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.2` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.2-publication.md).
npm alpha is alpha.2; latest remains alpha.1. Install explicit versions.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-911 alpha.3 preparation is merged through [PR #48](https://github.com/kefyusuf/surfacerelay/pull/48);
all 49 PR checks and merged-source Validate CI passed. Frozen source and final
artifacts are in the [publication handoff](docs/releases/0.1.0-alpha.3-publication-handoff.md).
New T-910 Filament exports remain absent from published alpha.1/alpha.2.

Docker preparation 195 Python/508 browser checks and canonical validation pass.
Canonical merged-source public artifacts pass clean browser import/types/bundle/
smoke/deep-import and 11 installed-Laravel HTTP/race tests. Installed Filament 45 browser/
160 Laravel files match; 23 HTTP + 7 fault controls and nine real Chrome native/
SQL cases pass. Independent review found no remaining byte/preparation blocker.
[Durable prepublication receipt](docs/reviews/alpha-0.1.0-alpha.3-prepublication.json).

## Next gate

Owner npm authentication is required: host whoami returned ENEEDAUTH; interactive
web login did not finish and was cancelled when it requested credentials. No
registry write, source tag or mirror push occurred. Complete human-controlled
login, then resume approved publication/registry gates. Do not start another task.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Both task containers and all owned demo/native tabs are removed. Final artifacts,
canonical source archive and unpublished local mirror remain in `.tmp/t911-session`
for publication; existing images/resources and both user Composer locks are kept.
No new image, named volume or worktree was created.
