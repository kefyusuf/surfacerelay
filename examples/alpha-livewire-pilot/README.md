# Registry-installed Livewire consumer pilot (T-906)

Disposable local Laravel 13 / Livewire 4 application installing both published
SurfaceRelay packages at `0.1.0-alpha.1` from registry locks. No Composer path
repositories, package source imports, Filament or payment integration.

The actual mounted component explicitly exposes `pilot.livewire.orders.hold`
with `{"reason":"customer-request"}`. The published Livewire binding producer
issues the exact component target and argument plan; the published browser driver
calls that component through Livewire. App-owned `HoldGateway` connects the method
to the installed ActionBus, authorization, confirmation, idempotency, output
redaction and audit stages. A hold inserts a simulated local effect, not a real
commerce operation. Ordinary replay preserves the server-owned intent key.

The actor, membership, tenant and current record are server-owned. Every request
checks the active component/binding/session and current trusted context. On remount
the previous component is invalidated. Approval matches the exact challenge shown
in the signed locked result; it stores an encrypted opaque receipt on the server
and creates no effect. Only retry can execute. Changed input or record requires
new confirmation. Revoked permission rejects even a previously approved/replayed
request. Livewire locked properties alone are not invocation authority.

## Run and stop

From the repository root in PowerShell:

```powershell
$docker = 'C:\Users\yukonit\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
& $docker compose -p surfacerelay-t906-livewire -f examples/alpha-livewire-pilot/compose.yml up -d
& $docker compose -p surfacerelay-t906-livewire -f examples/alpha-livewire-pilot/compose.yml logs --tail 30 app
```

Open <http://127.0.0.1:4186/> in the Codex in-app browser after initialization.
This workspace reuses the existing `surfacerelay-t807a-live:local` image: PHP
8.4.26, Composer 2.10.3, Node 22.23.3, Python 3.12.15, SQLite/mbstring/intl.
`SURFACERELAY_PILOT_IMAGE` can select an equivalent prepared image and
`SURFACERELAY_LIVEWIRE_PORT` can change the loopback port. This is a workspace
run recipe, not a portable image distribution. Source is mounted read-only;
dependencies, sessions and SQLite run in native container tmpfs and reset on
recreation. No persistent Docker volume is created.

```powershell
& $docker compose -p surfacerelay-t906-livewire -f examples/alpha-livewire-pilot/compose.yml exec app python3 tests/livewire_acceptance.py
& $docker compose -p surfacerelay-t906-livewire -f examples/alpha-livewire-pilot/compose.yml down --volumes --remove-orphans
```

The suite uses real signed Livewire HTTP requests and a real receipt expiry wait
(60 seconds locally, 5 seconds in CI). It queries the actual effect ledger and
compares the secret receipt's hash against public token candidates without
printing the receipt. Use only the project-scoped shutdown; do not prune Docker
or delete the shared image. The agent removes this task's stack after acceptance;
the earlier T-905 stack is preserved.

## Optional reproduction and limits

Seeded `owner`, `peer`, `denied` accounts use `@example.test` and public fixture
password `acceptance-password`. Owner belongs to both tenants, peer only A,
and denied has no hold permission. The key and credentials are disposable fixture
data, never deployment credentials. Sign in, let the native agent request a hold,
inspect the exact review, approve and retry. Refresh effects to inspect the ledger.
Record selection, permission toggle, tenant switch and remount are local test
controls; owner manual testing is not an acceptance requirement.

Evidence is bounded to one active component per session, one PHP HTTP worker and
this observed in-app browser. It is not independent human approval, general
multi-tab/concurrency qualification, WebMCP conformance, Filament qualification,
production authentication or a guarantee of one hold across deliberately new
intents. See [executed acceptance](../../docs/reviews/t906-livewire-acceptance.md).
