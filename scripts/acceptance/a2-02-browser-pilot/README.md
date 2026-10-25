# SurfaceRelay A2-02 B2 — app-owned browser descriptor and wiring

**State: HOSTED PASS — DESCRIPTOR EMISSION + BROWSER WIRING ONLY. Page-rendered descriptor delivery, real-browser WebMCP registration and real Chrome `document.modelContext` discovery/invocation remain NOT RUN (A2-08).**

Hosted PR-only evidence ([run 40239214917](https://github.com/kefyusuf/surfacerelay/actions/runs/40239214917), PR #54): `PASS_B2_DESCRIPTOR_EMISSION_ONLY` (3/3) and `PASS_B2_BROWSER_WIRING_ONLY` (15/15, driven by the server-issued descriptor document from the same run), plus A2-02a `PASS_SERVER_READ_ONLY` (12/12) and B1 `PASS_B1_BINDING_PRODUCTION_ONLY` (2/2) regressions on a registry-installed Laravel 13.38.0 / Filament 5.10.3 / Livewire 4.4.8 consumer.

Scope is deliberately limited to the consumer-owned browser half of A2-02 on top of the accepted A2-02a server read and A2-02b B1 binding production:

1. the mounted Filament `ListA2Orders` page issues the exact app-owned descriptor document (canonical `a2.orders.inspect@1` definition JSON plus the exact `LivewireBindingProducer` component binding);
2. a glue module consumes ONLY the published `@surfacerelay/browser-runtime@0.1.0-alpha.3` root export, pairs that definition and binding as `BoundActionTool`, and registers opt-in envelope-mode WebMCP tooling through `WebMcpRegistrationLifecycle`;
3. Node tests prove envelope return semantics (`returned` + `output.kind: 'undefined'` for the void server method; fixed safe guidance for `execution_failed`; `cancelled/not_dispatched`), fail-closed surfaces (unsupported surface, identity mismatch, stale component, expired binding, forged/missing input, pre-aborted signal), unmount lease disposal, and one shared `FilamentSelectionCoordinator` per Livewire environment.

No write path, new driver, runtime change, schema change, or release is authorized or included. The descriptor is public discovery metadata, never an authorization capability; every dispatch still re-resolves actor, tenant, record and binding server-side.

## Evidence boundary

- Real Chrome interoperability is **not** claimed here. The Node suite stubs `document.modelContext` and the global Livewire runtime; it runs the real published package. Native `document.modelContext` discovery/invocation proof is the separate A2-08 task.
- The Node suite requires `A2_DESCRIPTOR_FILE`: in CI this is the server-issued document written by `A2BrowserEmissionTest` in the same run. A locally generated descriptor is a developer harness input, never acceptance evidence.
- The emission test never accepts a caller-provided path: any non-empty `A2_DESCRIPTOR_OUT` exports the document to one fixed location under the consumer's `storage_path()` (`storage/framework/surfacerelay/a2-descriptor.json`), so path traversal is structurally impossible.

## Consumer-only changes

- `scripts/acceptance/a2-02-read-pilot/app/Filament/Resources/A2Orders/Pages/ListA2Orders.php`: additive `#[Locked] $surfacerelayDescriptor` property plus a `render()` emission step using the same `LivewireBindingProducer` call accepted under B1. No change to `inspectOrder`, existing state fields, or any security logic.
- `browser/a2-surfacerelay-glue.mjs`: the app-owned glue. Fails closed with `registered: false` on an unsupported surface; throws on definition/binding identity mismatch; never fabricates bindings.
- `browser/tests/a2-glue.test.mjs`: fifteen Node `node:test` cases consuming the real alpha.3 package.
- `check_php_junit.py` / `check_node_junit.py`: exact-identity JUnit receipts (`PASS_B2_DESCRIPTOR_EMISSION_ONLY`, `PASS_B2_BROWSER_WIRING_ONLY`). Any missing, failed, skipped or renamed test fails the receipt.
- `.github/workflows/a2-02-browser-b2-consumer.yml`: PR-only hosted proof; re-runs the accepted A2-02a (12 tests) and B1 (2 tests) suites as regressions before the B2 suites. Never `workflow_dispatch`; each commit must obtain its own CI evidence.

## Hosted execution (REQUIRED, NOT YET PERFORMED)

Open a PR from a branch named `test/a2-02-browser-b2-wiring`; the guarded workflow runs automatically on that branch only. It installs a fresh Laravel 13 + Filament 5 consumer from published alpha.3 registry packages via the A2-01 runner, applies the A2-02a overlay, and executes the four suites in order. Bounded JSON receipts and the server-issued descriptor document are uploaded as `a2-02-browser-b2-wiring-json` with 3-day retention; runtime package files, credentials and database content are not uploaded.

## Local reproduction

Local runs need PHP 8.4, Composer 2, Node 22+ and registry access; the browser half alone runs wherever Node runs. The path must not exist beforehand.

```sh
A2_ROOT="$(mktemp -d)/a2-02-browser-b2-consumer"
bash scripts/acceptance/a2-alpha3/run_a2_01.sh "$A2_ROOT"
python3 scripts/acceptance/a2-02-read-pilot/install_overlay.py "$A2_ROOT/app"
cp scripts/acceptance/a2-02b-binding-pilot/tests/Feature/A2BindingProductionTest.php \
  "$A2_ROOT/app/tests/Feature/A2BindingProductionTest.php"
cp scripts/acceptance/a2-02-browser-pilot/tests/Feature/A2BrowserEmissionTest.php \
  "$A2_ROOT/app/tests/Feature/A2BrowserEmissionTest.php"
cd "$A2_ROOT/app"
php artisan migrate --force
./vendor/bin/phpunit tests/Feature/A2OrdersInspectTest.php
./vendor/bin/phpunit tests/Feature/A2BindingProductionTest.php
A2_DESCRIPTOR_OUT=1 \
  ./vendor/bin/phpunit tests/Feature/A2BrowserEmissionTest.php

# Browser wiring half (uses the same server-issued descriptor document):
browser="$A2_ROOT/browser"
mkdir "$browser"
cp scripts/acceptance/a2-02-browser-pilot/browser/package.json "$browser/"
cp scripts/acceptance/a2-02-browser-pilot/browser/a2-surfacerelay-glue.mjs "$browser/"
mkdir "$browser/tests"
cp scripts/acceptance/a2-02-browser-pilot/browser/tests/a2-glue.test.mjs "$browser/tests/"
npm --prefix "$browser" install --no-package-lock --ignore-scripts --no-audit --no-fund
A2_DESCRIPTOR_FILE="$A2_ROOT/app/storage/framework/surfacerelay/a2-descriptor.json" \
  node --test --test-reporter=junit --test-reporter-destination="$A2_ROOT/node-junit.xml" \
  "$browser/tests/a2-glue.test.mjs"
python3 scripts/acceptance/a2-02-browser-pilot/check_node_junit.py \
  "$A2_ROOT/node-junit.xml" "$A2_ROOT/node-result.json"
```

Without hosted Laravel evidence, B2 stays `NOT RUN`; a green Node suite alone is not A2-02 B2 acceptance.
