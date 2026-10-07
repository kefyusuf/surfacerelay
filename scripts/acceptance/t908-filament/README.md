# Disposable T-908 Filament candidate consumer

This acceptance fixture extends the [T-907 generator](../t907-filament/README.md)
without changing its source, locks or destination. It retains the exact published
Laravel alpha.1 dependency and Filament 5.10.0 Composer lock. The browser dependency
is an explicitly supplied **local built candidate**, never claimed to be the
published npm alpha.1. It registers with
`new WebMcpRegistrationLifecycle(modelContext, drivers, { resultMode: 'envelope' })`.
The temporary consumer writes only simulated effects; no payment/provider exists.

Use the existing T-907 PHP/Composer/Node prerequisites and scoped Docker/browser
recipe. Do not start its old consumer as part of this task. From the repository:

```sh
python3 scripts/acceptance/t908-filament/test_generator.py
python3 scripts/acceptance/t908-filament/generate.py --browser-artifact /absolute/path/to/local-browser-candidate.tgz
cd .tmp/t908-filament-demo
composer install --no-dev --no-interaction --prefer-dist
composer validate --strict
npm install --no-audit --no-fund
npm ci --no-audit --no-fund
npm run build
php setup.php
php ../../scripts/acceptance/t907-filament/command.php filament:assets
PILOT_CONFIRMATION_TTL=60 php -S 127.0.0.1:4187 -t public public/router.php
```

The first npm install records the local candidate in a disposable lock; the old
published browser lock cannot describe an unreleased tarball. Source Composer
locks remain unchanged. When running in Docker, the candidate path must be visible
at the same path recorded in the generated `package.json`; alternatively regenerate
inside that container with its candidate path. Commands use POSIX environment
syntax; adapt shell syntax on Windows. `--refresh-owned` inherits the original
marker and redirected-entry checks, using `.t908-owned` and its own destination.

CI runs `python scripts/acceptance/t908-filament/verify_candidate.py
--require-clean-source` with Ubuntu, PHP 8.4, Node 22 and Python 3.12. The helper
requires absent task directories, checks tracked source against Git HEAD, builds
the private browser candidate as `0.0.0-t908.1`, installs/builds the locked
Filament consumer, and runs both HTTP suites through one owned server on port
8000 with five-second receipts. Its cleanup verifies ownership and removes only
the generated demo and `.tmp/t908-candidate`, terminating only its own server.
It proves signed HTTP and built-artifact integration; it does not run Chrome.

Run the preserved 23 HTTP controls and the additional five response-loss checks:

```sh
PILOT_URL=http://127.0.0.1:4187 PILOT_DATABASE="$PWD/database/acceptance.sqlite" \
  python3 ../../scripts/acceptance/t907-filament/filament_acceptance.py
PILOT_URL=http://127.0.0.1:4187 PILOT_DATABASE="$PWD/database/acceptance.sqlite" \
  python3 ../../scripts/acceptance/t908-filament/fault_acceptance.py
```

Run these sequentially. HTTP controls prove server behavior; they do not prove
native envelope visibility. Use the same `PILOT_CONFIRMATION_TTL` for the HTTP
tests and server. Native login is `/admin/login`, using the disposable
`owner@example.test` / `acceptance-password` credentials and actual Filament UI
checkboxes/Approve button.

## Exact post-commit response loss

The generated `ResponseLossFault` is demo-only gateway glue. It runs after the
real published Action Bus, confirmation, idempotency and output policy stages
produce a successful core result. A local SQL row selects the exact binding,
component, method and reason. The fault requires one HTTP POST to `/livewire/update`
containing exactly one component and exactly one matching call. It refuses to
discard responses that bundle unrelated Livewire work. Caller input cannot arm it.

Immediately before each actual Action Gateway dispatch it captures the ledger
count and last effect ID. It requires that **this invocation** produced exactly
one new effect matching its binding, action, actor, tenant, records and reason,
then atomically
consumes its one-shot SQL arm and returns HTTP 503 through the existing sanitized
exception handler. The effect and server result have already committed. This is
a lost-success-response simulation, not a rollback or real network outage. A
denial, approval-only result or idempotent replay cannot satisfy its effect check.
The arm-time `effects_before` remains diagnostic only; an earlier bundled effect
cannot make a later singleton replay qualify. The fifth HTTP regression covers
that exact sequence.

After native discovery/invocation has requested confirmation, approve through the
actual UI. Read the exact active binding ID from SQL, then arm only that binding:

```sh
python3 ../../scripts/acceptance/t908-filament/fault_control.py arm \
  --database database/acceptance.sqlite --binding-id THE_EXACT_BINDING_ID
```

The control refuses databases outside a marker-owned T-908 consumer and unknown,
expired or unsupported bindings. Its evidence contains ledger/fault fields only,
never receipts, snapshots or credentials. Repeated arming of the same ID fails;
select a fresh binding for another independent case. A nonmatching or bundled
request leaves the fault armed, so disarm it explicitly when abandoning a case.

Invoke the exact native tool once. The native result must visibly describe a
surface failure with unknown outcome. Immediately inspect evidence, wait without
issuing a retry, and inspect again:

The expected JSON is `kind: "surfacerelay.webmcp.execution.v1"`,
`status: "execution_failed"`, `outcome: "unknown"`, with error code
`execution_failed` and fixed message `Execution failed. The application outcome
is unknown. Verify application state before considering a retry.` Successful
callback resolution uses `status: "returned"` and
`output: {kind: "value", value: <original core result>}`. Inspect the nested
`value.status` for `confirmation_required`, `rejected` or `succeeded`.
Resolved undefined instead uses `output: {kind: "undefined"}`; it differs from
a returned value of null. Pre-aborted callback cancellation uses
`status: "cancelled"`, `outcome: "not_dispatched"`, error code
`execution_cancelled` and message `Execution was cancelled before driver dispatch.`
Driver cancellation after invocation remains conservatively unknown.

```sh
python3 ../../scripts/acceptance/t908-filament/fault_control.py evidence \
  --database database/acceptance.sqlite --binding-id THE_EXACT_BINDING_ID
```

Required SQL observations: `armed=0`, `effects_after=invocation_effects_before+1`, non-null
`dropped_at`, exactly one newly committed ledger effect with the intended tenant,
record IDs and reason; no further effect appears without another explicit call.
The HTTP test then deliberately replays the same authorized idempotent intent and
proves the existing one-effect behavior. This is a separate test step and never
adapter-issued recovery. Native acceptance must also retain successful results,
confirmation-required, permission denial, stale list/edit, tenant change and
expired receipt controls from T-907, with core results nested in the uniform
envelope. Outer native `Completed` alone does not prove application success.

## Cleanup

Close only task-created browser pages. Stop/remove only the task-named Compose
stack and its owned resources. Verify the resolved `.tmp/t908-filament-demo` path
and `.t908-owned` marker before removing that consumer. Preserve the T-907
consumer, user Composer locks, existing Docker resources and personal profiles.
