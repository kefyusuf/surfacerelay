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
is merged; merge-commit main CI passed. The registry-installed
Docker pilot recipe uses `http://127.0.0.1:4185/`; it is not running in the current
Docker inventory. Agent-led native in-app WebMCP
calls prove separate simulated 3D navigation, wrong/correct code, completion
and replay with one effect. Docker verification passes 12 checkout HTTP tests,
19 preserved HTTP tests, 6 Node DOM-wiring tests, build, strict Composer,
PHP syntax and canonical/22 HTMX fixtures. Independent static review has no
remaining blocker. TDD covers missing routes and a stale expiry observer race.
This app-owned HTTP pilot does not qualify real bank 3DS or Livewire/Filament
consumers. Single timing samples do not establish performance guarantees.
Owner manual testing is optional. User Composer locks and pre-existing Docker
resources are preserved; its startup recipe remains available for experiments.
T-906 local acceptance is verified: real Livewire 4.4.7/Laravel 13.35.0 consumer
using exact registry alpha packages, 15 signed HTTP checks, native in-app agent
approval/completion/replay and SQL one-effect proof. A stale displayed-challenge
approval race was fixed with observed RED/GREEN; independent review has no
remaining blocker. Acceptance is complete; [PR #42](https://github.com/kefyusuf/surfacerelay/pull/42)
is merged; merge-commit main CI passed. Its task-owned Docker
container/network were removed after verification; T-905 is preserved. Filament remains
outside T-906. T-907 HTTP acceptance passes: exact registry Filament 5.10.0 consumer,
23 HTTP tests, 3 generator safety tests, fresh locked install/build and
canonical validation. Mixed-selection and superseded-modal regressions were
observed RED and fixed in the consumer; independent review has no blocker.
Real Filament form login now establishes a membership-checked demo tenant;
anonymous and nonmember requests are covered. T-907 acceptance is complete through
the owner-authorized Chrome alternative: native discovery, UI selection 101+102,
approval-only behavior, exact edit102, replay, changed selection, permission
revocation/fresh discovery, tenant switch, stale pages and receipt expiry all have
agent/SQL evidence. Native operations added exactly two effects; rejected and
approval-only steps added none. Fresh Docker 23 HTTP checks, 3 generator safety
tests and canonical validation pass; independent source/evidence review has no
blocker. Codex in-app loopback access still fails with `ERR_BLOCKED_BY_CLIENT` and
is not qualified by Chrome results. [PR #43](https://github.com/kefyusuf/surfacerelay/pull/43)
remains unmerged. Demo, helper, Compose file, owned app/network, two task volumes
and seven test tabs were removed. Existing image, unrelated resources and user
lockfiles are preserved. No additional task, release or automatic merge is selected.
See [acceptance evidence](docs/reviews/t905-checkout-acceptance.md) and
[guide and shutdown](examples/alpha-pilot/README.md).
See [Livewire evidence](docs/reviews/t906-livewire-acceptance.md) and
[Livewire startup recipe](examples/alpha-livewire-pilot/README.md).
See [Filament evidence](docs/reviews/t907-filament-acceptance.md) and
[disposable recipe](scripts/acceptance/t907-filament/README.md).
See [release details and installation](docs/releases/0.1.0-alpha.1-publication.md).
