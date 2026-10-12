# SurfaceRelay A2-02b / B1 — Mounted binding production compatibility

**State: B1 HOSTED PASS — BINDING PRODUCTION ONLY. Browser invocation, WebMCP registration and B2 remain NOT RUN.**

Scope is deliberately limited to one published-package `LivewireBindingProducer` call for the existing read-only `a2.orders.inspect@1` Action Definition on the actual mounted Filament `ListA2Orders` page. No browser registration, agent endpoint, runtime changes, binding verification authority, write path, release, or WebMCP claim.

## Evidence baseline

- `main` at preparation time: `9cafd9f50e46b36908232bbf278f52be6a95149f`.
- A2-02a's 12 Laravel tests and 27 assertions passed on [hosted run #38238374460](https://github.com/kefyusuf/surfacerelay/actions/runs/38238374460). Do not relabel that proof as B1.
- Before B1, `inspectOrder(int $orderId)` mismatched the `ActionDefinition.inputSchema.properties.order_id` name, which the *published* `LivewireMethodCallPlanBuilder::forExposure` compares exactly. That baseline failure is a **source-derived prediction**, not a recorded hosted RED PHPUnit run.

## Consumer-only changes

- `scripts/acceptance/a2-02-read-pilot/app/A2/InspectOrder.php`: extract the existing literal Action Definition into `public static function definition(): ActionDefinition` and use that same factory in `dispatch()`. No change to its ID, version, schema, risk or authorization.
- `scripts/acceptance/a2-02-read-pilot/app/Filament/Resources/A2Orders/Pages/ListA2Orders.php`: rename `$orderId` to `$order_id` only, preserving positional Livewire invocation, `void` return, state fields, and all server-side security logic.
- `scripts/acceptance/a2-02b-binding-pilot/tests/Feature/A2BindingProductionTest.php`: two hosted-only tests: a schema/parameter signature regression and one test using the **actual mounted Filament page**, `LivewireActionExposureReader`, `MethodLivewireComponentIdentityResolver`, `RandomBindingIdGenerator`, and `LivewireBindingProducer`. Assert one server-issued binding with identity `a2.orders.inspect@1`, exact component ID, driver `livewire`, lifecycle `component`, method `inspectOrder`, `inputOrder=['order_id']`, `requiredCount=1` and no claimed TTL.

The first B1 test inspects the real page method signature and the canonical Action Definition, so it fails on the original `$orderId` parameter without requiring a browser. The second exercises real issued binding production against a mounted Filament component. Neither test supplies server-side binding token authority.

## Hosted PR-only execution proof and local reproduction

The PR-only [B1 registry-backed consumer workflow](../../../.github/workflows/a2-02b-b1-consumer.yml) ran successfully on [GitHub Actions #38245000968](https://github.com/kefyusuf/surfacerelay/actions/runs/38245000968), source SHA `2fe5b545f9aa336edcb7d72551fa458fa3f0dc59` (before this documentation-only update): A2-02a regression **12/12 tests, 27 assertions**, and B1 mounted binding production **2/2 tests, 13 assertions**. The proof reports `PASS_SERVER_READ_ONLY` and `PASS_B1_BINDING_PRODUCTION_ONLY`, respectively. Package verification also passed (`PASS_LOCK_METADATA_ONLY` and `PASS_CRITICAL_SUBSET`). Bounded proof artifact `a2-02b-b1-mounted-binding-json` is attached to that run; this workflow runs only for this draft PR branch, not by manual dispatch. Each new commit must obtain its own CI evidence.

To reproduce the same tests locally, reuse an independent fresh Laravel 13 + Filament 5 consumer from published alpha.3 registry packages. Apply the overlay, copy the two-case B1 test into the disposable consumer, and run both PHPUnit suites:

```sh
# Run from a trusted repo checkout with the B1 patch and required PHP, Composer,
# Node and registry access. The path must not exist beforehand.
A2_ROOT="$(mktemp -d)/a2-02b-b1-consumer"
bash scripts/acceptance/a2-alpha3/run_a2_01.sh "$A2_ROOT"
python3 scripts/acceptance/a2-02-read-pilot/install_overlay.py "$A2_ROOT/app"
cp scripts/acceptance/a2-02b-binding-pilot/tests/Feature/A2BindingProductionTest.php \
  "$A2_ROOT/app/tests/Feature/A2BindingProductionTest.php"
cd "$A2_ROOT/app"
php artisan migrate --force
./vendor/bin/phpunit tests/Feature/A2OrdersInspectTest.php
./vendor/bin/phpunit tests/Feature/A2BindingProductionTest.php
```

The B1 test lives **outside** `a2-02-read-pilot/app`, `database`, and `tests` trees because its installer deliberately copies exactly eight overlay files; this keeps the already accepted A2-02a 12-test verification contract unchanged. A2-02a `check_junit.py` remains unchanged.

**B1 PASS has been observed on hosted Laravel/Filament**, with both B1 tests and the previous 12 tests passing. The current branch HEAD requires its own CI success before review promotion; PHP syntax and source-level shape checks alone do not meet this gate. Never reinterpret `RuntimeBinding` as a server-signed or session-authorizing capability. Browser return semantics (B2) are deliberately deferred.
