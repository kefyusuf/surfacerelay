# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.1` remains published on npm/Packagist. Exact source/mirror/tarball
provenance and registry acceptance are in the [publication receipt](docs/reviews/alpha-0.1.0-alpha.1-publication.json).
npm's `alpha` and automatically added `latest` point to that experimental release;
use the explicit alpha version. No alpha.2 tag or registry write has occurred.

## Current task

T-909: owner-approved coordinated `0.1.0-alpha.2` preparation, publication and
fresh registry verification for Laravel/browser only. [Frozen scope and gates](docs/releases/0.1.0-alpha.2.md).
Source metadata remains development-only; existing private builder/preview
NO-GO guards stay unchanged. No MCP/OpenAPI promotion or new adapter/API work.

T-908 is complete: [PR #44](https://github.com/kefyusuf/surfacerelay/pull/44) merged
and all main CI workflows passed. The opt-in browser execution envelope preserves
default/direct-driver behavior, nests original application values and reports
unknown driver-failure outcomes without inspecting errors or retrying.
[Native/SQL evidence](docs/reviews/t908-native-acceptance.md) includes a committed
effect with lost response, exact replay, stale/permission/tenant controls and
verified approved-receipt expiry. No further T-908 implementation is pending.

Alpha.2 preparation observed README RED/GREEN: 12 public-artifact tests and
194 Python regressions pass; canonical fixtures/guardrails pass. Docker public
npm import/types/bundle/smoke/deep-import checks and 11 installed Laravel HTTP/
shared-store checks pass, with the authorization mutation detected. All 36 browser
dist files match the T-908 native-qualified candidate; Laravel runtime/license
bytes match alpha.1. Fresh Chrome/SQL alpha.2 local-consumer checks pass for
normal approval, post-commit response loss, explicit replay and stale binding.
[Preparation evidence](docs/reviews/alpha-0.1.0-alpha.2-preparation.md).
Existing CI release jobs take one explicit alpha.2 version. Independent source
review passed; PR CI, final merged-source rebuild and registry/mirror gates remain.
The default host npm session is unavailable; owner interactive authentication
will be needed before publication.

## Limits and cleanup

T-905/T-906/T-907 recipes remain available; their acceptance is complete.
Previous task demos/resources were removed. T-909 uses only its own verification
containers; existing image/resources and two user Composer locks are preserved.
No unresolved public-contract decision. Experimental prerelease: no stable API,
production-security, support SLA, performance or general native interoperability
claim. Chrome qualification does not repair in-app loopback access.
Do not begin the next development task automatically.
