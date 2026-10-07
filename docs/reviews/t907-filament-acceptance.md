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

## Native browser gate

After the Codex app restart, browser control initializes and a temporary blank
tab can be created and closed. The previous trusted Node initialization failure
is no longer reproduced. Native acceptance remains BLOCKED: navigation to the
running demo on port 4187 returns `net::ERR_BLOCKED_BY_CLIENT`, including the
nonredirecting `/session` route via both `127.0.0.1` and `localhost`. Independent
host HTTP access to `/session` returns 200. No cause or browser repair is inferred.

The continuation also reproduced missing `/admin/login` (404), incorrect anonymous
panel exception handling (500), and an empty tenant after actual signed Filament
form authentication. The consumer now uses real Filament login with its own
middleware, preserves Laravel's normal AuthenticationException handling and checks
membership before setting the default demo tenant. Missing membership logs out and
invalidates the session. All 23 HTTP checks pass, including actual form login,
authorized discovery, anonymous redirects/401 and nonmember rejection; all three
generator safety checks and canonical/22 HTMX fixtures pass. PHP syntax passes.
No Chrome fallback is used. HTTP checks cannot qualify browser-side Alpine
selection synchronization, native tool discovery or agent-driven execution.

## Limits and cleanup

Explicit checkbox selection only; tracking all records is outside this demo.
D-075 stays proposed app/example glue. There is no public contract change,
Filament production qualification, release or automatic merge authorization.
The pre-existing image is reused; unrelated Docker resources and user lockfiles
remain untouched. Scoped cleanup verified: the generated demo, temporary Compose
file, `surfacerelay-t907-filament-app-1` and its project network were removed.
Baseline, clean-consumer and final-contract containers used `--rm` and are gone.
No new image, volume or worktree was created. The prior T-905 container was not
running in the fresh inventory and was not restarted. User package lockfiles remain.
The continuation's `surfacerelay-t907-native` stack, generated demo, temporary
Compose file and browser tab were also removed; its ephemeral verification
containers are gone. The reused image and unrelated runner fleet remain.
Concurrency, crash recovery and production correctness are not qualified here.
