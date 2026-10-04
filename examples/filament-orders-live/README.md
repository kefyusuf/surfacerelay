# Filament Orders — Live WebMCP Proof (T-807a/b)

Real-browser evidence for the [Filament order operations reference](../filament-orders/README.md): a real Filament 5 panel served by `testbench serve`, real Livewire 4, the SurfaceRelay browser runtime, and Chromium's native `document.modelContext`.

It is a fixture, not a starter app. The trusted actor and tenant are fixed to `tenant-a` by the existing `FilamentOrderDemoHarness`; the panel has no login.

## What it proves

`tests/filament-webmcp.spec.mjs` plays the agent through the browser's own `getTools()` / `executeTool()`:

| Scenario | Result |
| --- | --- |
| Orders table | Only `tenant-a` rows (101–103) are rendered; one tool `orders.refund_selected.v1` is registered from the server-issued Livewire binding. |
| Edit page, `orders.hold_current.v1` | Holds exactly the trusted current record (101) end to end and returns `{orderId: 101, held: true}`. |
| Human ticks 101 + 102, agent calls refund | Stops at `{status: "confirmation_required"}`; the real Filament confirmation modal opens; nothing is refunded. |
| Human clicks **Approve**, agent retries | Refunds exactly 101 and 102 once (`{refundedCount: 2, orderIds: [101, 102]}`); a further call needs a new confirmation. |
| Selection or input changes after approval | New confirmation required; nothing is refunded. |
| Agent repeats the call without approval | Never refunds. |
| Agent adds `confirmed: true` or `confirmationReceipt` | Rejected by the Livewire driver before any request. |
| Human ticks and unticks, agent calls refund | Fails closed (no trusted selection). |
| Agent adds `orderIds: [201, 202]` | Rejected by the Livewire driver before any request. |

## Selection sync (finding)

Filament 5 keeps table selection in Alpine and pushes it to the server only when a table action is mounted (`filament/tables` `table.js`, `mountAction`). An agent calling the page method directly therefore reached the server with an **empty** `current_selection` (`required_context_missing` in the audit trail) even though the human saw two rows selected — and could, in principle, reach it with a stale one.

`client.mjs` wraps the Livewire driver so that, before each call, it pushes the Alpine selection to `$wire` exactly as Filament's own `mountAction` does. The server's `current_selection` is then the selection the human sees at invocation time. This grants no new authority — page script could always set that property — and the server still authorizes every selected record against the trusted tenant, all-or-nothing. Proposed as D-075.

## Approved retry (T-807b)

`ListOrders::refundSelected(reason)` still accepts only business input; the opaque receipt is never a page-method argument (D-040/D-051). When the human approves in the modal, `InteractsWithSurfaceRelayConfirmation` keeps the receipt in server-side session state keyed by the approving component, and only the page's own protected `pullApprovedSurfaceRelayConfirmationReceipt()` hands it to the gateway on the next call, once. It never appears in public Livewire state and is not browser-callable. The confirmation stage still consumes it only for the exact scope (actor, tenant, selection, input, surface, binding). Proposed as D-076.

Issue, approval and retry are three Livewire requests, so the fixture uses a file-cache `ConfirmationService` instead of the harness's in-memory one.

## Run locally

Requires PHP 8.3+ with `pdo_sqlite`, Composer, and Node 22.

```bash
cd packages/laravel
composer update
cd ../../examples/filament-orders-live
npm ci
npx playwright install chromium
npm test
```

Set `PHP_BINARY` if `php` is not on `PATH`. The Playwright web server runs `testbench workbench:build`, `filament:assets` and `testbench serve` on `127.0.0.1:4180` from `packages/laravel` (see `testbench.yaml`). Runtime JavaScript is compiled into `.tmp/runtime` and served by the fixture provider in `packages/laravel/tests/Browser`.

## Limits

One flag-enabled Chromium build, the page acting as tool caller, no real AI agent, fixture-fixed actor/tenant, a per-request in-memory idempotency store, and a fixed fixture binding id in the gateway call. Not WebMCP conformance and not production setup.
