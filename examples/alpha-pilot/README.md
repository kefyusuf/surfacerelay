# Registry-installed alpha browser pilot (T-905)

Local-only, disposable Laravel application using the published
`surfacerelay/laravel:0.1.0-alpha.1` and
`@surfacerelay/browser-runtime@0.1.0-alpha.1`. Dependencies are installed from
Packagist/npm, without monorepo source or Composer path repositories.

The first pilot uses an app-owned HTTP browser driver. It proves the common
trusted execution pipeline; it is not a Livewire/Filament integration proof,
production application, payment processor or native-agent certification.
Refunds only insert effects in a disposable SQLite database.

T-905 acceptance is agent-led. The manual steps below are optional reproduction
instructions, not a requirement for the owner to operate every test.

## Agent-led order checkout and separate 3D simulation

The explicitly registered `pilot.orders.pay` tool accepts only
`{"method":"test-card"}`. The server resolves the current order, membership,
payment permission and fixed simulated amount (1999 minor units, TRY). A caller
cannot supply code, receipt, confirmation flags or payment authority to the tool.

The first call requires confirmation and exposes a separate local 3D page link
in the order desk. The agent follows that visible link; the page has a code
field. Disposable `111111` is wrong, `123456` is correct. Three incorrect codes
fail the flow. Pending flows expire after three minutes. Correct-code verification
creates no payment; it issues a real runtime confirmation receipt encrypted in
server-owned storage. The agent returns to the order desk, discovers the fresh
document-bound tool, and retries. Receipt scope and current authorization are
rechecked, and the normal installed-package pipeline executes the simulated
payment. A replay must return the safe completed result without another effect.

Use **Start new simulated checkout** only to deliberately start a separate test
intent. Ordinary retries preserve the server-owned flow and idempotency key.
This is exactly-once evidence for a checkout flow; it is not a complete commerce
domain or a claim that arbitrary new flows cannot pay the same seeded record.

The correct code is deliberately public fixture data, so the agent may enter it
for these tests. A real bank challenge, real SMS code or real human approval
cannot be replaced by this simulation. There is no bank/SMS/card integration.
Expired or failed flow retries reject execution; the tool bridge can present a
generic invocation error. **Refresh checkout status** reports the safe current
flow state; an invocation error alone must never be interpreted as payment success.

See [executed acceptance and timing limits](../../docs/reviews/t905-checkout-acceptance.md).

## Start and stop

From the repository root in PowerShell:

```powershell
$docker = 'C:\Users\yukonit\AppData\Local\Programs\DockerDesktop\resources\bin\docker.exe'
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml up -d
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml logs --tail 30 app
```

Open <http://127.0.0.1:4185/> in the Codex in-app browser after initialization
finishes. Use that browser for native WebMCP tests. The host port binds
only to loopback. This workspace reuses its pre-existing
`surfacerelay-t807a-live:local` image (PHP 8.4.26, Composer 2.10.3, Node 22.23.3,
Python 3.12.15, SQLite/mbstring/intl). Override `SURFACERELAY_PILOT_IMAGE` with an
equivalent prepared image, or `SURFACERELAY_PILOT_PORT` before starting.
This is a workspace run recipe, not a portable image distribution.

Application/dependencies/database run on container-native temporary storage.
Restarting or recreating the container reinstalls dependencies and resets data.
The host source is mounted read-only. No persistent Docker volume is created.

After manual testing:

```powershell
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml down --volumes --remove-orphans
```

Do not remove the shared pre-existing image or run global Docker prune commands.

## Manual acceptance

Use only disposable seeded accounts. All use `acceptance-password`:

| Account | Membership and permission |
| --- | --- |
| `owner@example.test` | Tenants A and B; refund permission |
| `peer@example.test` | Tenant A only; refund permission |
| `denied@example.test` | Tenant A; no refund permission |

For each test, record the visible result and effect count before/after. A refund
means one effect record, not a financial transaction. Never include session
cookies, CSRF tokens or raw confirmation receipts in feedback.

1. **Authentication:** A wrong password must reject login. Log in as owner;
   tenant A must show only orders 101/102.
2. **Tenant isolation:** Switch owner to tenant B; only order 201 must appear.
   Log in as peer and try tenant B; access must be rejected.
3. **Human confirmation:** Select an order, request a refund; the result must
   require confirmation and effect count must stay unchanged. Approve and retry
   promptly; one effect must appear.
4. **Idempotency:** Retry the same successful request with the same key and
   intent; the result must succeed without another effect. Start a fresh intent
   only when deliberately testing a separate simulated refund.
5. **Permission:** Log in as denied and request a refund; it must be rejected,
   with no additional effect.
6. **Changed approval scope:** Obtain approval, change selection or reason, then
   retry; old approval must not execute the changed intent.
7. **Expired approval:** Approve, wait more than 60 seconds, then retry; no effect
   may execute using that receipt. A fresh confirmation may be required.
8. **Binding lifecycle:** Use the local test control to expire a binding, then
   invoke; the stale binding must reject execution. Establish a fresh session
   before continuing.

The UI offers local test controls and a result/effect observer for these seeded
records. Their presence is acceptance instrumentation, not a production API.
Ordinary browsers can test HTTP/runtime and confirmation behavior. Native WebMCP
tests use the Codex in-app browser. Availability must be observed separately;
lack of native support is not a failed HTTP pilot. A passed local agent call
qualifies only this pilot and this browser, not general interoperability.

## Native WebMCP manual test in Codex

1. Open the pilot in the in-app browser and sign in as Owner. Confirm the page
   reports an authorized native tool registration.
2. Ask Codex to invoke the page's refund tool for the current selection with
   reason `customer-request`. Before approval, expect `confirmation_required`
   and no new effect.
3. Inspect the pending order/tenant/reason summary and personally click
   **Approve in this browser**. Approval alone must leave effects unchanged.
4. Tell Codex to retry the same tool and intent. Expect one simulated effect.
   Ask for the same retry again; the effect count must remain unchanged.
5. For a negative check, start a new request, approve, then select another order
   and click **Apply selected orders** before asking for retry. Expect a new
   confirmation with the new server-issued summary, with no extra effect.

Changing the page state can replace tool registrations. Codex must use the
currently advertised document-bound tool, never substitute a new binding for an
old captured handle. Receipts are supplied by the page-owned flow, not chat.

## Automated checks

Inside the running app container:

```powershell
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml exec app python3 tests/pilot_acceptance.py
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml exec app python3 tests/checkout_acceptance.py
& $docker compose -p surfacerelay-t905-pilot -f examples/alpha-pilot/compose.yml exec app node --test tests/pilot_ui.test.mjs
```

The HTTP suite includes a real receipt expiry wait (61 seconds at the manual
pilot's default TTL). It also uses SQLite directly to count effects globally,
so session changes cannot hide unauthorized side effects. Node checks use the
real published runtime with a deterministic DOM/HTTP stub; they are not browser
or native WebMCP evidence. The CI pilot job uses a separate disposable database
and a five-second TTL.
The checkout suite adds twelve real HTTP/state checks, including a real receipt
expiry wait, two-worker completion replay and a deterministic stale-expiry race.

## Feedback format

```text
Browser/version:
Scenario number:
Steps (account, tenant, selected order, reason):
Expected:
Observed:
Effect count before -> after:
Visible result/error:
```

Send one block per failure, with a screenshot if helpful. Do not paste the full
Network request headers or authentication/confirmation values.
