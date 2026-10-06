# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

- `0.1.0-alpha.1` is published for [Laravel](https://packagist.org/packages/surfacerelay/laravel)
  and [browser runtime](https://www.npmjs.com/package/@surfacerelay/browser-runtime).
  T-901–T-904 are complete; preparation/tooling PRs #36–#39 are merged.
- Both packages come from the exact `v0.1.0-alpha.1` source tag. The Laravel
  distribution mirror's matching tag/commit and all 160 file hashes are verified.
- npm's downloaded tarball matches the reviewed SHA-256 and 39-file manifest.
  Its `alpha` tag points to the release. npm also added `latest` and rejected
  removing it with HTTP 400; use the explicit alpha version/tag.
- Fresh registry consumers pass browser import/typecheck/bundle/smoke/deep-import
  rejection and Laravel's 11 HTTP tests, authorization mutation and two-process
  confirmation/idempotency races. Tagged-mirror and local artifact checks pass too.
- Verification before publication: 187 Python tests, canonical/22 HTMX fixtures,
  strict Composer, guardrails, independent review, tooling PR 41/41 and final
  main-source CI 20/20. See [durable receipt](docs/reviews/alpha-0.1.0-alpha.1-publication.json).
- Owner `kefyusuf` used interactive 2FA. Unattended token/OIDC provisioning is
  deferred; private source builders/readiness/preview guards stay unchanged.
- Private security intake is enabled. D-069–D-074/D-076–D-078 are Accepted;
  D-026/D-075 remain Proposed.

## Limits and next work

This is an experimental prerelease, without stable API, production-security or
support-SLA guarantees. Native-browser/real-agent qualification limits remain.
T-905 implementation and acceptance are complete; [PR #41](https://github.com/kefyusuf/surfacerelay/pull/41)
is ready for the owner-authorized merge, subject to final-head CI. The registry-installed
Docker pilot runs at `http://127.0.0.1:4185/`. Agent-led native in-app WebMCP
calls prove separate simulated 3D navigation, wrong/correct code, completion
and replay with one effect. Docker verification passes 12 checkout HTTP tests,
19 preserved HTTP tests, 6 Node DOM-wiring tests, build, strict Composer,
PHP syntax and canonical/22 HTMX fixtures. Independent static review has no
remaining blocker. TDD covers missing routes and a stale expiry observer race.
This app-owned HTTP pilot does not qualify real bank 3DS or Livewire/Filament
consumers. Single timing samples do not establish performance guarantees.
Owner manual testing is optional. User Composer locks and pre-existing Docker
resources are preserved; the pilot stays running for further experiments.
No next task is active. The next candidate is registry-installed Livewire or
Filament consumer qualification; its scope must be defined before implementation.
See [acceptance evidence](docs/reviews/t905-checkout-acceptance.md) and
[guide and shutdown](examples/alpha-pilot/README.md).
See [release details and installation](docs/releases/0.1.0-alpha.1-publication.md).
