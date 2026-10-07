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

Alpha.2 preparation observed README RED/GREEN: 12 public-artifact tests pass
after replacing the incorrect first-alpha claim. Existing CI release jobs now
take one explicit alpha.2 version; full candidate/consumer verification, review,
final exact-source CI and live registry/mirror authority checks are pending.
The default host npm session is unavailable; owner interactive authentication
will be needed before publication.

## Limits and cleanup

T-905/T-906/T-907 recipes remain available; their acceptance is complete.
Previous task demos/resources were removed. T-909 uses only its own verification
container; existing image/resources and two user Composer locks are preserved.
No unresolved public-contract decision. Experimental prerelease: no stable API,
production-security, support SLA, performance or general native interoperability
claim. Chrome qualification does not repair in-app loopback access.
Do not begin the next development task automatically.
