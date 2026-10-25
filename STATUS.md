# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

A2-02 B2, app-owned browser descriptor and wiring — hosted-verified on
[PR #54](https://github.com/kefyusuf/surfacerelay/pull/54). The A2-02
read-only consumer's mounted Filament page issues the exact descriptor
document (canonical definition JSON plus the B1-accepted
`LivewireBindingProducer` component binding), and the app-owned glue plus a
fifteen-case Node suite pass against the published
`@surfacerelay/browser-runtime@0.1.0-alpha.3` root export: envelope return
semantics, fail-closed unsupported/mismatched/stale/expired/forged surfaces,
lease disposal and shared coordinator exclusion. Hosted receipts:
descriptor emission 3/3 and browser wiring 15/15, with A2-02a 12/12 and B1
2/2 regressions on a registry-installed Laravel 13 / Filament 5.10 / Livewire
4 consumer. Real Chrome `document.modelContext` proof remains A2-08. No
write path, new driver, runtime change or release is in scope.

Known unrelated breakage: main's floating `filament/filament: ^5.0` drifted;
`FilamentConfirmationApprovalIntegrationTest` fails three tests on every
`validate` matrix cell since 2026-10-12. Needs a separate owner decision.

## Next gate

Owner review and merge decision on
[PR #54](https://github.com/kefyusuf/surfacerelay/pull/54); the PR's B2
workflow is green, while `php-tests` stays red from the known unrelated
Filament drift on main. After the merge decision, select the next task
explicitly. No further registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2-02
consumer overlay and B1 test are tracked acceptance consumers, not runtime code.
