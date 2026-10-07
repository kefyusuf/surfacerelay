# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.1` is published for [Laravel](https://packagist.org/packages/surfacerelay/laravel)
and [browser runtime](https://www.npmjs.com/package/@surfacerelay/browser-runtime).
Exact tag/mirror/tarball provenance and registry consumer checks are recorded in
the [publication receipt](docs/reviews/alpha-0.1.0-alpha.1-publication.json).
npm's `alpha` and automatically added `latest` point to that experimental release;
use the explicit alpha version. No subsequent release has started.

## Current task

T-908 implementation and local acceptance are complete under accepted D-079.
[PR #44](https://github.com/kefyusuf/surfacerelay/pull/44) adds explicit opt-in
`resultMode: 'envelope'` at WebMCP registration. Default behavior and direct
drivers remain compatible; core contracts and server authority are unchanged.
Returned application values stay nested; generic failure reports unknown outcome,
and only callback pre-abort reports no driver dispatch. No automatic retry.

Docker checks pass: 447 browser tests/typecheck/build, 193 Python tests, canonical
validation, 22 HTMX and 41 envelope fixtures, 23 existing + 7 new Filament HTTP
checks, 5 generator checks and 4 CLI checks. A clean committed-source CI-helper
run builds and installs the candidate successfully. Real isolated Chrome/SQL
acceptance proves safe stale failures, permission denial, tenant change, expired
receipt renewal, and committed-effect/response-loss uncertainty with one effect
after explicit authorized replay. Independent source/evidence review has no blocker;
reviewers did not replay native calls. [Evidence](docs/reviews/t908-native-acceptance.md)
records the qualified candidate revision and limits. Hosted final-head CI is a
separate gate on the PR.

T-905/T-906/T-907 acceptance is complete and PRs #41–#43 are merged.
Their recipes remain available; no task demo is running. T-908 demo/candidate,
two containers, one network, two volumes, eight native test tabs and owned helpers
were removed. Existing image/resources and user Composer locks are preserved.

## Needs decision and limits

No unresolved T-908 contract decision. Next step is final PR CI/review and an
owner-authorized merge. Publication is separate; npm/Packagist still serve alpha.1.
Do not start another task automatically. This is an experimental prerelease;
serial simulated-effect tests do not qualify concurrency, production payments,
performance or general native interoperability. In-app browser loopback remains
unqualified; accepted Chrome results do not repair that environment.
