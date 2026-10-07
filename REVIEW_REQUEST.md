# Temporary Registry-installed Filament Acceptance Review

Task: T-907, locally accepted through the owner-authorized Chrome alternative.
Branch: `feat/t-907-temporary-filament-acceptance`.
Review handoff: [PR #43](https://github.com/kefyusuf/surfacerelay/pull/43).

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
The active agent used official Chrome DevTools MCP native discovery/invocation
with real UI selection and visible approval. SQL proves exactly two native effects:
selected 101+102 and edit102. Approval alone, replay, changed intent/selection,
revoked permission, tenant switching, stale pages and expired receipt add no effect.
Independent source/evidence review has no blocker; native calls were not separately
replayed by the reviewer. Stale HTTP failures appear as native Error with empty
errorText; console HTTP statuses and SQL establish rejection. Fresh-document
discovery is separate from the unchanged registry of an already open document.
Codex in-app loopback still fails with `ERR_BLOCKED_BY_CLIENT`; Chrome results
do not qualify that surface. CI is a separate gate from native browser evidence.

No public contract/core change, package publication or production qualification
is included. D-075 remains proposed app/example glue. The
temporary demo, test control/helper and Compose file were removed, along with the
owned container/network, two task volumes and seven test browser tabs.
Reproducible recipe/locks/tests remain. Existing Docker image, unrelated resources
and user lockfiles are preserved; no new image or worktree was created. The local
Chrome connection is version-pinned with an isolated profile and port 4187 allowlist.
