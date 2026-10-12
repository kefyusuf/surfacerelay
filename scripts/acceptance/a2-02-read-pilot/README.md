# SurfaceRelay A2-02a — independent authenticated Filament read pilot

**State: IMPLEMENTATION PREPARED / NOT EXECUTED AGAINST REAL LARAVEL.**

This is a disposable **consumer application overlay**, authored independently from the T-907/T-910 fixture generators and using only the package's public PHP classes. It is **not** a change to SurfaceRelay, **not** an A2-02 acceptance PASS, and **not** production-ready integration code. It is deliberately the smallest **server-side** slice of A2-02: one actual Filament Resource page dispatches one harmless Action Definition through the released `FilamentActionGateway` / `ActionBus`, with actual Laravel validation, Gate authorization, membership-derived tenant, output policy and audit. The remaining native binding/WebMCP proof is not implemented and is still `NOT RUN`.

## Source + release prerequisites

The published A2-01 independent consumer run [#38221296185](https://github.com/kefyusuf/surfacerelay/actions/runs/38221296185) passed on 2026-10-12: Laravel `13.35.0`, Filament `5.10.1`, Livewire `4.4.7`, `surfacerelay/laravel:0.1.0-alpha.3`, `@surfacerelay/browser-runtime@0.1.0-alpha.3`, lock metadata and nine critical release hashes. This overlay is for a **new disposable consumer** with those exact SurfaceRelay versions. Do not use a `path`, `artifact`, `vcs` or custom Composer mirror to simulate registry adoption.

The bootstrap must have a registered `App\Providers\Filament\AdminPanelProvider`; A2-01 reported a *possible provider-registration warning* during `filament:install`. The overlay refuses a missing registration rather than silently repairing it or turning a 404 into a false SurfaceRelay product defect.

## File plan

- `app/A2/SingleMembershipTenantResolver.php`: derives tenant only from one authenticated user's database membership; missing or ambiguous membership fails closed
- `app/A2/InspectOrder.php`: canonical read-only `a2.orders.inspect@1` with six real pipeline stages, audit and trusted Filament gateway; no browser binding yet
- `app/Providers/A2OrdersProvider.php`: tenant + record scoped Laravel Gate
- `app/Models/A2Order.php`, `app/Filament/Resources/A2Orders/*`: ordinary Filament resource and Livewire page method, one explicit action exposure attribute
- `database/migrations/*`: only two synthetic fixture tables; SurfaceRelay's own audit tables come from its package migrations
- `tests/Feature/A2OrdersInspectTest.php`: eleven proposed hosted Laravel tests, including tenant, missing membership, malicious extra field, guest, invalid ID and audit
- `install_overlay.py`: refuse to overwrite any file or redirected installation target, refuses wrong version, unsupported source or missing Filament panel

No agent-only HTTP endpoint, new driver, scope/confirmation contract or write operation is introduced. There is **no WebMCP lifecycle registration yet**. The `ExposeAction` attribute is only metadata and **does not issue or authorize a binding**.

## Local syntax and installer checks — already performed

The authoring environment supports PHP 8.4 syntax checking and Python but does not have Composer, PHP SQLite/mbstring modules, or npm/Packagist connectivity. These checks passed here:

```sh
find . -type f -name '*.php' -exec php -l '{}' \;
python3 -m unittest -q verify_overlay.py test_check_junit.py
```

**Result: 8 PHP lint checks; 16 local acceptance-tool tests PASS** (11 installer + 5 JUnit receipt-parser). Synthetic installer tests do not instantiate Laravel, hit a database or prove actual Filament/Livewire behavior.

## Hosted A2-02a execution proposal

A companion, manual-only GitHub Actions workflow proposal is packaged at
`.github/workflows/a2-02-read-consumer.yml` for review. It is **not deployed**.
It reuses A2-01's exact public npm/Packagist installation, applies this
standalone overlay, migrates only a disposable SQLite consumer and executes
11 PHPUnit test cases. `check_junit.py` fails if any expected test is missing,
failed or skipped; a green JUnit result is recorded as `PASS_SERVER_READ_ONLY`
**not** full A2-02 acceptance. Four bounded JSON receipts are eligible for
3-day artifact retention; runtime package files, credentials and database
content are not uploaded. Ordinary CI may run on the preparatory PR/merge
because `validate.yml` watches pushes and PRs even if this new workflow does not.

The workflow must first be reviewed/merged to the default branch under
separate permission; a second decision is needed before manual dispatch.
Do not force a green test by silently changing the package API or mutating a
production installation.

## Hosted or full local run — REQUIRED and NOT YET PERFORMED

In a trusted, disposable Ubuntu/WSL environment with PHP 8.4, Composer 2, Node 22, SQLite/mbstring/zip and registry access:

```sh
# In a clean surfacerelay source checkout at/after PR #51, for the A2-01
# runner scripts only; this does not install a local SurfaceRelay dependency:
A2_ROOT="$(mktemp -d)/consumer"
bash scripts/acceptance/a2-alpha3/run_a2_01.sh "$A2_ROOT"

# Extract this ZIP outside the repo, e.g. /tmp/surfacerelay-a2-02-read-pilot
python3 /tmp/surfacerelay-a2-02-read-pilot/install_overlay.py "$A2_ROOT/app"
cd "$A2_ROOT/app"
php artisan migrate --force
php artisan test tests/Feature/A2OrdersInspectTest.php
```

**Fail closed if `bootstrap/providers.php` does not list `AdminPanelProvider::class`.** Recheck the Filament install and register its generated provider in the *disposable consumer* only; do not modify SurfaceRelay source or weaken the test to get green. Inspect each failed test, including Filament panel mount/API compatibility, before classifying `DOCS` vs published-product `FAIL`.

A green eleven-test Laravel result is **A2-02a SERVER READ PASS**, not the complete A2-02 or native WebMCP acceptance. Record exact package versions, SQL audit results, commands, public API imports and negative cases in a limited JSON receipt. Delete only the newly created disposable test directory, after evidence capture and verified ownership.

## Additional A2-02a negative tests (v2)

The server-only test suite now declares **11** PHPUnit tests. Added checks:
revoked membership denies with an audit event; ambiguous membership in two
eligible tenants fails closed without a silent tenant choice; an extra caller
`tenant_id` is removed by the Laravel validation stage and cannot override
trusted tenancy; an authenticated member can mount the actual Filament page by
HTTP. These are test expectations, **not observed runtime results**.

A separate manually-triggered hosted workflow proposal is included in the
companion acceptance patch; merge and dispatch are separate approval gates.

## Security assertions that must hold

1. `a2.orders.inspect@1` uses a real `ActionDefinition`, `ActionRegistry`, `ActionBus` and `FilamentActionGateway` with a **real active Page**.
2. Trusted actor comes from `LaravelAuthenticatedActorResolver` (`web` guard); tenant from membership DB, **never action input**.
3. Validation returns an allowlisted `order_id`; Laravel Gate requires both membership and an order belonging to that tenant. Execution queries the same scoped relation again.
4. Allowed own order returns only `orderId` and `found`. Denied/foreign/missing-member calls must yield no order payload. No business row writes; audit event is an intended append.
5. A forged `tenant_id` submitted with a foreign `order_id` cannot change trusted tenant or pass Gate.
6. No binding identity/expiry/session/selection authority is claimed by this server-only slice. The browser/cross-surface path and positive/negative binding cases must be implemented and tested **separately**, without trusting caller-supplied binding metadata.

## Decision boundary

- **PASS A2-02a** only with actual Laravel/Filament tests and audit evidence after exact registry install.
- **DOCS** if consumer bootstrap steps are missing but published APIs suffice.
- **FAIL** only if a minimal independently reproduced released-package defect remains after validating consumer wiring.
- **BLOCKED/NOT RUN** until hosted execution is available or if environment cannot run.

No PR, branch, tag, release or workflow dispatch was performed as part of this local overlay. Continue with the same single acceptance gate only after evaluating the first mounted invocation.
