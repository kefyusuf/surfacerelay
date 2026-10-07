# Temporary Registry-installed Filament Acceptance Review

Task: T-907, HTTP verified / native blocked.
Branch: `feat/t-907-temporary-filament-acceptance`.

The selected scope is a temporary loopback Docker Filament application inside
the repository using exact published alpha packages. Current record and selected
records must run through real mounted resource pages, the shipped Filament gateway,
confirmation bridge, Livewire binding producer and ActionBus pipeline. T-906/PR #42
is merged and merge-commit main CI passed.

Review signed stale-component replay, exact binding/session/tenant/current-record
scope, discovery versus invocation authorization, permission revocation, runtime
receipt confidentiality, approval-only behavior and server-side idempotency.
Livewire locked public properties must not be mistaken for current server authority.

Fresh locked registry install, strict Composer, build, 23 HTTP checks,
3 generator safety tests, PHP syntax and canonical/22 HTMX fixtures pass.
TDD reproduced and fixed mixed-tenant partial execution and superseded modal A
approval after B was issued. Approval alone has no effect; tested completion/replay
has one effect. Independent static review has no remaining blocker. See
[executed evidence](docs/reviews/t907-filament-acceptance.md).

Real Filament form login now sets the default demo tenant after checking membership;
anonymous redirects, JSON rejection and nonmember logout are tested.
Native browser control initializes after restart, but Codex in-app loopback
navigation is BLOCKED by `ERR_BLOCKED_BY_CLIENT`. No native proof
is inferred from HTTP checks. The task stays open for real browser discovery,
Alpine selection synchronization and lifecycle evidence. CI is a separate HTTP gate.

No public contract/core change, package publication, production qualification or
automatic merge is selected. D-075 remains proposed app/example glue. The
temporary demo and Compose file were removed, along with the owned container/network.
Reproducible recipe/locks/tests remain. Existing Docker image, unrelated resources
and user lockfiles are preserved; no new image, volume or worktree was created.
