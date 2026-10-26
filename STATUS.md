# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-912 Filament notification drift repair. Main's floating
`filament/filament: ^5.0` resolved a Filament 5.10.2+ release whose scoped
`NotificationMailbox` is bound only by the notifications service provider;
the confirmation approval test registered only Livewire's provider, so
`send()` and `assertNotified()` resolved separate mailbox instances and
`php-tests` failed three tests in every matrix cell since 2026-10-12. The
test now registers the Filament support and notifications providers and
uses array cache/session/mailbox stores. Production code and the published
`^5.0` support range are unchanged. Verified: full Laravel package suite
612 tests / 3272 assertions OK (2 expected MySQL skips) on Filament 5.10.3
in Docker, and the confirmation test 6/6 on Filament 5.9.0.

## Next gate

Hosted `php-tests` matrix green on this PR, then the owner merge decision;
afterwards [A2-02 B2 PR #54](https://github.com/kefyusuf/surfacerelay/pull/54)
is unblocked. No further registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
The owned registry container/native tabs and both task staging folders are removed.
Existing images/resources and
both user Composer locks are kept. No new image, named volume or worktree was created.
