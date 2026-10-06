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
T-905 is ready for owner manual acceptance: the registry-installed Docker pilot
is running at `http://127.0.0.1:4185/` in the Codex in-app browser. Docker checks:
19 HTTP tests, 4 Node DOM-wiring regressions, browser build, strict Composer,
PHP syntax and canonical/22 HTMX fixtures pass. Independent static review has
no remaining blocker. Native in-app WebMCP calls prove confirmation-required,
approved execution, idempotent replay and changed-selection reconfirmation.
The pilot uses an app-owned HTTP adapter; Livewire/Filament consumer qualification
remains separate. Owner feedback and current PR CI are pending. User Composer
lockfiles and pre-existing Docker resources are preserved. The pilot stack stays
running for manual tests; [guide and shutdown](examples/alpha-pilot/README.md).
See [release details and installation](docs/releases/0.1.0-alpha.1-publication.md).
