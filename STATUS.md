# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

T-913 — verified minimal quickstart (selected; implementation starting). `main`
is green and the A2 series is complete: server read (A2-02a), binding
production (B1), browser wiring (B2, [PR #54](https://github.com/kefyusuf/surfacerelay/pull/54))
and the real Chrome native proof (A2-08, [PR #56](https://github.com/kefyusuf/surfacerelay/pull/56),
local native receipts plus hosted `PASS_A2_08_PAGE_WIRING_ONLY`) are merged.
The quickstart addresses the adoption gap: one consumer document whose code —
one read-only Action Definition, one explicit exposure, one self-guarding
registration module — is proven verbatim by a fresh registry consumer in CI.
Hosted receipts are GREEN on
[PR #57](https://github.com/kefyusuf/surfacerelay/pull/57)
(`PASS_T913_QUICKSTART_READ_ONLY` 5/5 plus `PASS_T913_GLUE_NODE_ONLY` 3/3);
README now points new adopters at the quickstart. Read-only scope; no runtime,
schema, fixture or release change.

## Next gate

Owner review and merge decision on
[PR #57](https://github.com/kefyusuf/surfacerelay/pull/57). After the merge,
the highest-value next step is the first real external consumer, not new
features.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2 acceptance
consumers are tracked acceptance tooling, not runtime code.
