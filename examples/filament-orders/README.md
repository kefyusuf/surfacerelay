# Filament Multi-Tenant Order Operations Reference

This directory documents the executable T-505 reference vertical. It is **not** a standalone demo application or a production deployment scaffold. The executable proof lives in the Laravel Testbench fixtures and integration tests under `packages/laravel/tests/**`.

## What this demo proves

T-505 combines the existing Filament trust controls in one realistic multi-tenant order workflow without adding a Filament-specific execution driver or a second business path.

```text
trusted actor + trusted tenant
        │
        ▼
exact active Filament Page
        │
        ├── current_record
        ├── current_selection
        └── filament/active_filters
        │
        ▼
FilamentActionGateway
        │
        ▼
existing ActionBus
        │
        ├── validation
        ├── authorization
        ├── idempotency
        ├── confirmation
        ├── execution
        ├── output policy
        └── structured audit
```

The demo deliberately treats ordinary Filament query scoping as defense-in-depth rather than as the final authorization boundary. Every mutation separately verifies exact record/selection membership against the trusted tenant before application execution.

## Seed layout

The integration fixture uses five orders:

| Tenant | Order | Status |
|---|---:|---|
| tenant-a | 101 | paid |
| tenant-a | 102 | paid |
| tenant-a | 103 | pending |
| tenant-b | 201 | paid |
| tenant-b | 202 | pending |

The table resource normally scopes its Eloquent query to the trusted active tenant.

## `orders.hold_current`

The record-page operation accepts only business intent:

```json
{
  "reason": "manual-review"
}
```

The authoritative order comes from trusted `current_record`, not from caller input, request/query data, or metadata. The operation requires:

- authenticated actor;
- trusted tenant;
- exact current record;
- Laravel Gate authorization checking both actor/tenant membership and record/tenant membership.

The negative proof assigns a persisted Tenant B order directly to the record-aware test page while the trusted actor/tenant remain Tenant A. This bypasses normal resource-query scoping on purpose. Authorization still fails before executor mutation.

## `orders.refund_selected`

The bulk operation also accepts only business intent:

```json
{
  "reason": "customer-request"
}
```

Target records come from the exact Filament `current_selection` snapshot. Applied filters are exposed independently through the existing trusted runtime extension:

```text
filament/active_filters
```

The action is an external side effect with consequential risk and required-key idempotency. It requires authenticated actor, tenant, current selection, and human confirmation.

Caller metadata such as the following is intentionally non-authoritative:

```json
{
  "orderIds": [201],
  "tenantId": "tenant-b",
  "filters": {"status": "pending"}
}
```

Tests prove that the exact selected Tenant A records and the exact applied `paid` filter remain authoritative instead.

## Atomic tenant authorization

Bulk authorization is all-or-nothing. If one selected record does not belong to the trusted tenant, the whole operation is denied. The implementation does not silently remove unauthorized records and continue with an authorized subset.

A test-only host-query misconfiguration switch deliberately permits a mixed `[101, 201]` table selection while trusted tenant authority remains Tenant A. The Laravel Gate still rejects the complete invocation before confirmation or execution. This is the defense-in-depth proof: UI query scoping is useful, but mutation authorization does not depend on it being perfect.

## Confirmation is approval-only

The refund flow preserves the T-504 model:

```text
refund request
   ↓
confirmation_required
   ↓
approve exact challenge
   ↓
NO refund execution yet
   ↓
caller explicitly retries original operation
   ↓
fresh trusted-context resolution
   ↓
exact-scope receipt consumption
   ↓
refund execution
```

Approval never calls the order executor and never redispatches the business action.

The tests also prove that an approved receipt is not spent by a wrong-scope attempt. Selection, tenant, or applied-filter drift produces a new confirmation requirement; restoring the exact original trusted scope allows the original receipt to complete.

## Idempotency

`orders.refund_selected` uses the existing required-key idempotency pipeline.

The executable proof covers:

- exact approved retry executes the refund side effect once;
- a completed lost-response retry replays without a second executor call or a second confirmation;
- changed validated input under the same key conflicts;
- changed selection or applied-filter authority changes intent and cannot replay the previous output;
- tenant changes use the existing trusted authority partition rather than sharing a cross-tenant idempotency namespace.

Raw idempotency keys remain invocation-envelope candidates and are not Action input or target authority.

## Human/agent convergence

The record and list pages expose the same page methods through the existing Livewire binding machinery:

```text
EditOrder::holdCurrent(reason)
ListOrders::refundSelected(reason)
```

`#[ExposeAction]`, `LivewireActionExposureReader`, `LivewireBindingProducer`, and `MethodLivewireComponentIdentityResolver` produce normal `driver=livewire` bindings. There is no `filament` RuntimeBinding driver.

Page methods delegate through `OrderDemoPageActions`; they contain no direct Eloquent mutation, `ActionBus`, or `ActionCall` shortcut. The non-consequential hold method is executed through that same page-method → gateway → ActionBus → executor seam.

The Testbench fixture does not configure a full Filament panel container. Therefore the method-execution proof boots the Filament page directly instead of rendering it through `Livewire::test()`. Binding production itself is tested with the production Livewire binding producer. A full application may add a panel-level browser/render test without changing this authority model.

## Durable audit secrecy

The demo uses the existing T-404 migration plus `DatabaseAuditEventStore`; it does not define a second audit schema.

Adversarial integration markers are placed into tenant, record status, refund reason, applied filter, raw idempotency key, and the exact confirmation receipt. Persisted audit rows are then inspected directly.

The proof requires all raw marker bytes to be absent while the existing allowlisted provider manifest remains visible, including facts such as:

```text
order_demo.actor
order_demo.tenant
filament.current_record
filament.current_selection
filament.active_filters
surfacerelay.confirmation
```

The confirmed retry records `human_confirmation_present=true` without persisting the bearer receipt. Demo correlation IDs are internal sequence identifiers and are not derived from order IDs, business input, or idempotency keys.

## Run the executable proof

From the repository root:

```bash
cd packages/laravel
composer test -- --filter FilamentMultiTenantOrderOperationsDemoTest
composer test -- --filter FilamentOrderDemoLivewireBindingTest
```

The repository validation workflow additionally exercises PHP 8.3/8.4 across Illuminate 12/13, Composer validation, PHP lint, the frozen contract validator, browser-runtime typecheck, and browser-runtime tests.

## What this is not

T-505 does not add:

- a generic multi-tenancy package;
- a Filament RuntimeBinding driver;
- a new ActionBus execution path;
- agent-only order endpoints;
- caller-authoritative order IDs, tenant IDs, selected IDs, filters, confirmation booleans, or challenge IDs;
- a new confirmation/idempotency/audit state machine;
- production order-domain code;
- changes to `spec/0.1/**` or browser-runtime production code.

The fixture is a reference vertical showing how an application can compose the existing SurfaceRelay primitives while keeping target and authorization authority server-side.