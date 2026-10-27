# Project Status

Current state only; history is in Git, PRs and the archive.

## Current release

`0.1.0-alpha.3` is published for `@surfacerelay/browser-runtime` and
`surfacerelay/laravel`; [release record](docs/releases/0.1.0-alpha.3-publication.md).
npm alpha is alpha.3; latest remains alpha.1. Install explicit versions.
T-910's opt-in Filament root exports are available; Laravel runtime is unchanged.
Development metadata/private NO-GO guards remain; MCP/OpenAPI are outside release.

## Active task

A2-08 — real Chrome `document.modelContext` discovery/invocation proof on the
A2-02 read-only consumer. LOCAL NATIVE PASS, receipts committed in
[scripts/acceptance/a2-08-native-chrome](scripts/acceptance/a2-08-native-chrome/README.md):
on Chrome 155 with the WebMCP feature and the official chrome-devtools-mcp
1.10.1 agent surface, the app-owned page module registers the server-issued
descriptor, `list_webmcp_tools` exposes exactly `a2.orders.inspect.v1`
(readOnly), `execute_webmcp_tool` returns the B2 envelope and creates a server
audit row (same server operation as the human route), and the negatives pass:
forged/missing input fail closed, the foreign-tenant denial is proven in
server-owned page state, unmount disposes the lease, re-registration works,
and an unauthenticated visitor sees no tools. Consumer-only changes: render
hook page wiring, self-guarding registration module, staged setup scripts,
hosted Chrome-free wiring tests. Hosted wiring receipts pending on
[PR #56](https://github.com/kefyusuf/surfacerelay/pull/56).

## Next gate

Hosted page-wiring receipts green on
[PR #56](https://github.com/kefyusuf/surfacerelay/pull/56), then the owner
merge decision; after that the next task is selected explicitly (minimal
quickstart per the progression plan). No further registry write is required.

## Limits and cleanup

Experimental prerelease; no production/payment, SLA, performance or general native
interoperability claim. Helper exclusion does not cover ordinary UI calls; deferred
writes are not transactional. Unknown outcomes require state inspection before retry.
Existing images/resources and both user Composer locks are kept; the A2-02
consumer overlay and B1 test are tracked acceptance consumers, not runtime code.
