# Review handoff

Open PR: [PR #56](https://github.com/kefyusuf/surfacerelay/pull/56) from
`test/a2-08-native-chrome`. A2-08 — real Chrome `document.modelContext`
discovery/invocation proof on the A2-02 read-only consumer — has a LOCAL
NATIVE PASS with committed receipts
([pilot](scripts/acceptance/a2-08-native-chrome/README.md),
`proof-result.json`, `guest-result.json`): Chrome 155 + WebMCP feature,
official chrome-devtools-mcp 1.10.1 agent surface. Discovery exposes exactly
`a2.orders.inspect.v1` (readOnly); invocation returns the B2 envelope and
creates a server audit row (same server operation as the human route);
forged/missing input fail closed; the foreign-tenant denial is proven in
server-owned page state while the void-method envelope stays `returned`
(documented semantics); unmount disposes the lease; re-registration works;
an unauthenticated visitor sees no tools.

Consumer-only change set: a `BODY_END` render hook injecting one import map
(published runtime bytes) plus a self-guarding registration module; staged
consumer setup scripts; a hosted Chrome-free wiring test
(`PASS_A2_08_PAGE_WIRING_ONLY` pending on this PR's workflow) with A2-02a/B1/B2
regressions. No runtime, schema, fixture or release bytes changed.

Security review focus: the render hook and module deliver discovery metadata
only — registration requires both a native `document.modelContext` and a
server-issued descriptor; the live guest negative shows no tools; the
foreign-tenant denial stays server-owned; the invocation audit row proves the
agent route reaches the same trusted pipeline. The former
`php-tests` red is already fixed by merged T-912 (PR #55).
