# T-906 registry-installed Livewire consumer acceptance

Status: Local acceptance verified on 2026-10-06; PR integration/CI is separate.

## Selected boundary

The standalone Docker consumer installs the published Laravel and browser-runtime
`0.1.0-alpha.1` packages from their registries. An actual Livewire component exposes
one consequential current-order hold action through a server-issued component
binding and the installed browser driver. The app supplies trusted authentication,
tenant/current-record context, confirmation transport and an effect ledger.

No Filament, bank integration, public package contract change or publication is
part of this task. T-905's HTTP pilot and its accepted separate 3D simulation are
distinct evidence. Owner manual testing is optional.

## Test-first baseline

Ten real signed Livewire HTTP tests were written before the business methods.
The mounted minimal component shell supplied real framework snapshots. Docker
RED: ten failures in 0.928 seconds because the requested business/approval methods
were absent. Livewire rendered the missing-method failure as HTTP 419; this was
checked against the installed implementation rather than assumed to be CSRF.

## Executed verification

- Installed locks: Laravel 13.35.0, Livewire 4.4.7, SurfaceRelay Laravel/browser
  runtime `0.1.0-alpha.1`; registry archives, no path repositories/source coupling.
- Review-found stale displayed-challenge RED: old signed snapshot approval returned
  200 instead of 409 (0.134 seconds). Matching the locked displayed challenge
  against the current stored challenge made it GREEN (0.095 seconds).
- Tenant switch RED: missing route returned 404 instead of 200; focused GREEN
  after implementation (0.207 seconds). New trusted tenant requires remount.
- Fourteen real signed HTTP checks passed in 62.536 seconds with the local
  60-second receipt TTL. Final complete 15-case suite passed in 7.556 seconds
  with a second temporary PHP process and the CI 5-second TTL; the expiry check
  waited on the actual package receipt clock in both runs.
- Negative coverage: changed input/record, stale component/snapshot, different
  session/actor/tenant, guest/denied actor, revoked permission, foreign record,
  locked-field manipulation, extra positional authority and stale displayed review.
- Actual server-held receipt hash is compared against public token candidates
  in signed snapshots, rendered HTML, results and audit without printing the token.
- Strict Composer, browser build, PHP source syntax and startup shell syntax pass.
  Canonical validation/22 HTMX fixtures pass. Independent static review has no
  remaining blocker after the displayed-challenge correction.

## Actual native in-app agent evidence

The agent invoked the registered `pilot.livewire.orders.hold.v1` through the
Codex in-app WebMCP capability and actual mounted Livewire component. Order 101
required approval, completed and replayed the same safe result. Selecting order
102 required a new exact review. SQL counted three effects before and after
approval; completion plus native replay left four total effects, exactly one
for order 102. Approval alone did not execute.

Revoking permission removed the native tool; its captured handle rejected.
Calling the actual Livewire button directly still returned canonical
`authorization_denied`, proving discovery removal is not the authorization gate.
Restoring permission registered a new tool. Remount rejected the old document/
component handle. Switching to tenant B rendered current order 201 and its fresh
native call required new confirmation; total effects stayed four.

Native handles prove browser registration lifecycle; real signed HTTP tests
separately prove server rejection when a caller bypasses discovery. The local
tenant selector initially displayed A after switching to B; a small Blade
selection fix was verified in-app to match the trusted B context after reload.

## Environment and limits

Scoped Docker project: `surfacerelay-t906-livewire`, loopback port 4186. The existing
T-905 pilot on 4185 and user Composer locks are preserved. Local fixture credentials
and an app approval button are simulation data; they do not establish independent
human approval or production authentication qualification. Task-owned Docker
container/network were removed after verification. The temporary baseline and
final-contract containers were automatically removed; no image, volume or worktree
was created. Existing T-905 resources and shared image remain intact. See the
[startup/shutdown recipe](../../examples/alpha-livewire-pilot/README.md).
