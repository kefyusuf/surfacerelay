# T-907 temporary Filament consumer acceptance

## Scope

A disposable application is generated inside `.tmp/t907-filament-demo/` from the
existing registry-installed Livewire fixture and application-specific Filament
templates. It uses real resource list/edit pages, the published Filament gateway,
confirmation bridge, Livewire binding producer and ActionBus pipeline. The effect
ledger simulates order operations; no bank or external commerce system is used.

The owner requires removal of the generated demo after testing. Durable recipe,
locks and executable acceptance checks remain under
`scripts/acceptance/t907-filament/`. The generator is not a production starter.

## Executed evidence

- Registry installation: Filament 5.10.0, Laravel 13.35.0, Livewire 4.4.7 and
  `surfacerelay/laravel` 0.1.0-alpha.1. Browser runtime is pinned to 0.1.0-alpha.1.
- Initial real signed list/edit invocation tests observed RED: HTTP 200 returned
  the deliberate `not_implemented` result instead of `confirmation_required`.
  Minimal page methods then delegated to the shipped Filament gateway.
- A second observed RED exposed partial selection: Filament filtered a foreign
  tenant key out of a mixed request and executed the remaining approved key.
  The app now checks the whole explicit selection before pulling approval authority.
- Review requested a same-binding superseded-modal regression. A pending modal A
  was canceled/unmounted, B was issued with another reason, then the old signed A
  snapshot approved and executed A (RED, one unexpected ledger effect). The app
  now uses the existing protected confirmation-service hook to compare the shown
  locked challenge with the current binding registry and current permission before
  delegating approval. No published package or contract was changed.
- Independent HTTP inspection found one binding script on the rendered panel;
  CSS, Filament table JavaScript and built runtime JavaScript returned HTTP 200
  with the correct content types. This is not browser execution evidence.
- Baseline canonical schema and 22 HTMX fixtures pass.
- Worker verification passes 20 real signed HTTP checks, including list101+102
  approval/retry/replay with one ledger effect, exact edit102 effect, revoked
  permission, changed selection, mixed tenant keys, expired receipt/binding,
  tenant/session/remount boundaries, locked caller authority and stale modal A.
- Root independently repeated a fresh Composer/npm locked registry install,
  strict Composer validation, build and all 20 HTTP checks (9.530 seconds) in a
  disposable clean container. All three actual generator filesystem safety tests
  passed (1.038 seconds); PHP syntax and final canonical/22 HTMX checks pass.
- Actual approved receipt fingerprints were compared with candidate bearer strings
  in complete approval/execution responses and all persisted audit rows, without
  printing the bearer. No receipt or raw output marker was disclosed.
- Independent final static review has no remaining blocker. The HTTP suite and
  generator checks are included in the `filament-http-consumer` CI job. CI success
  is a separate result and cannot close the native gate.

## Native Chrome acceptance

The owner authorized Chrome as the alternative native acceptance surface and
approved the official Chrome DevTools MCP connection. The project-local connection
pins version 1.10.1, launches an isolated headless profile with WebMCP enabled and
allows only localhost/127.0.0.1 port 4187. Arbitrary evaluation and telemetry are
disabled. The active agent used `list_webmcp_tools` and `execute_webmcp_tool`, not
page-local discovery or a JavaScript invocation substitute. The Codex in-app
loopback `ERR_BLOCKED_BY_CLIENT` remains a separate unresolved environment limit;
these results do not establish in-app browser qualification.

Fresh Docker installation/build and strict Composer validation passed. All 23
mounted HTTP checks passed in 89.665 seconds with receipt TTL 60; three generator
safety tests and canonical validation including 22 HTMX fixtures passed.
Real Filament form login established the membership-checked tenant. Browser
discovery exposed `pilot.filament.orders.refund.v1` on the list and
`pilot.filament.orders.hold.v1` on the edit page.

The SQL ledger had six effects from HTTP verification before native operations.
Each native boundary was checked against the same ledger:

| Native scenario | Observed result | Total effects |
| --- | --- | --- |
| UI selects 101+102; native refund | `confirmation_required`, visible modal | 6 |
| Click visible Approve | No business effect | 6 |
| Native approved retry | `succeeded`, exact orderIds 101+102 | 7 |
| Native replay | Same result, no duplicate effect | 7 |
| Change reason after completed intent | `idempotency_conflict` | 7 |
| New list page, invoke old page | Native error; browser console HTTP 409 | 7 |
| Fresh list approval, deselect 102 before retry | Fresh `confirmation_required` | 7 |
| Edit 102, visible approval, native retry | `succeeded`, exact orderIds 102 | 8 |
| Open edit 101, invoke old edit 102 | Native error; browser console HTTP 409 | 8 |
| Approve edit 101, revoke membership permission, retry | `authorization_denied` | 8 |
| Fresh document while permission revoked | No native tools discovered | 8 |
| Restore permission, approve fresh edit 101, switch tenant B | Old native call fails; browser console HTTP 404; fresh list shows only 201 | 8 |
| Approve fresh tenant B selection; wait beyond receipt TTL 60 seconds; retry | Fresh `confirmation_required`, no execution | 8 |

Permission revocation/restoration was a controlled mutation of the disposable
membership row. Tenant switching used a temporary authenticated HTML form with
Laravel CSRF protection posting to the fixture's existing membership-checked
`/tenant` endpoint. It did not issue a new binding before the old call was tested.
That test-only route is removed with the generated demo. No production tenant UI
or extra published API was added. The expiry retry occurred more than 60 seconds
after visible approval and returned a distinct challenge without an effect.

The browser adapter reports stale HTTP failures as native `Error` with an empty
errorText. Console HTTP status and SQL checks supply the rejection evidence; no
structured core rejection is inferred for these transport failures. Discovery on
an already open document is not automatically refreshed after permission changes.
Fresh-document discovery and invocation authorization were verified separately.
Blocked external-font requests appear in the console under the local allowlist;
local panel assets and native operation are functional. No performance guarantee
is inferred from these calls.

## Limits and cleanup

Explicit checkbox selection only; tracking all records is outside this demo.
D-075 stays proposed app/example glue. There is no public contract change,
Filament production qualification, release or automatic merge authorization.
The pre-existing image is reused; unrelated Docker resources and user lockfiles
remain untouched. Native verification uses the task-owned
`surfacerelay-t907-native` stack with temporary vendor/node_modules volumes.
Cleanup verified: the app container, network and both volumes were removed with
`docker compose -p surfacerelay-t907-native down --volumes --remove-orphans`.
The marker-owned generated demo, test helper, temporary Compose file and seven
test browser tabs were removed. The isolated connection retains only its original
blank tab. Generator/contract containers used `--rm` and are gone. Independent
source/evidence review found no blocker; it did not repeat the native calls.
No new image or worktree was created. The prior T-905 stack was not restarted.
Concurrency, crash recovery and production correctness are not qualified here.
