# Registry-installed Livewire Consumer Review

Task: T-906, acceptance complete. [PR #42](https://github.com/kefyusuf/surfacerelay/pull/42)
is owner-approved for integration after final-head CI passes.

The selected scope is a standalone loopback Docker Laravel/Livewire application
using the exact published alpha packages. A tenant-scoped consequential order
action must run through a real mounted Livewire component, server-issued binding,
published browser driver and installed ActionBus trust pipeline. T-905/PR #41 is
merged and its merge-commit main CI passed; its app-owned HTTP proof remains separate.

Review signed stale-component replay, exact binding/session/tenant/current-record
scope, discovery versus invocation authorization, permission revocation, runtime
receipt confidentiality, approval-only behavior and server-side idempotency.
Livewire locked public properties must not be mistaken for current server authority.

Verification passes: 15 real signed Livewire HTTP checks, browser build, PHP
syntax, strict Composer, startup shell syntax and canonical/22 HTMX fixtures.
Actual native Codex in-app calls prove approval-only behavior, completion/replay,
record/tenant changes, tool removal on revocation and stale handles after remount.
SQL proves one effect for the tested order-102 intent. A displayed-challenge
approval race was observed RED, fixed with exact shown/stored challenge matching
and verified GREEN. Independent static review has no remaining blocker.
See [executed evidence](docs/reviews/t906-livewire-acceptance.md). Final-head CI
must pass before integration; merge-commit CI remains separate from local/native proof.

No Filament integration, public contract/core change, new package publication or
production qualification is selected. Owner manual testing is optional. Preserve
the existing T-905 stack and user Composer locks. The scoped T-906 container/network
were removed after verification; no new image, volume or worktree was created.
