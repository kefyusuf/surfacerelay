# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.2` is published on npm/Packagist for `@surfacerelay/browser-runtime`
and `surfacerelay/laravel`. Source/mirror tags, final archive/manifest bytes,
registry-installed consumers and bounded Chrome/SQL acceptance are verified in
the [release record](docs/releases/0.1.0-alpha.2-publication.md) and
[publication receipt](docs/reviews/alpha-0.1.0-alpha.2-publication.json).
npm `alpha` points to alpha.2; `latest` remains alpha.1. Install explicit versions.
Source metadata remains development-only; private builder/preview guards remain
NO-GO. MCP/OpenAPI packages are outside this release.

## Current task

T-909 release gates are complete. Preparation [PR #45](https://github.com/kefyusuf/surfacerelay/pull/45)
merged after green CI and review; its merge is the frozen release source.
This documentation branch records actual publication and verification results;
its own review/CI is separate from the already published source.

The browser now ships T-908's explicit opt-in execution envelope. Default/direct
driver contracts remain compatible. Inspect the nested application result;
native Completed is not business success. Unknown outcomes require checking
application state before any explicit retry. Laravel runtime bytes are unchanged
from alpha.1. [Migration guide](docs/consumers/browser-runtime.md).

## Limits and next scope

Experimental prerelease: no stable API, production-security guarantee, support
SLA, performance or general native interoperability claim. Native proof uses a
serial simulated Filament ledger; shared-store races are separately bounded.
Chrome qualification does not repair Codex in-app loopback access.
No unresolved release gate or public-contract decision. No next development
task is selected; plan D-075 helper/productization separately before coding.

## Cleanup

Task-owned Docker verification/native/registry containers, generated demos,
test tabs and temporary publication resources are removed after retaining the
durable receipts. Existing images/resources and two user Composer locks remain.
No self-created worktree remains. Do not begin another development task automatically.
