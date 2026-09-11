# Filament Multi-Tenant Order Operations Reference

This directory documents the executable T-505 reference vertical. It is **not** a standalone demo application or a production deployment scaffold. The executable proof lives in the Laravel Testbench fixtures and integration tests under `packages/laravel/tests/**`.

## What this demo proves

T-505 combines existing Filament trust controls in one realistic multi-tenant order workflow without adding a Filament-specific execution driver or a second business path.

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

Ordinary Filament query scoping is treated as defense-in-depth rather than as final mutation authorization. Every mutation separately verifies exact record/selection membership against the trusted tenant before application execution.

## Seed layout

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

The authoritative order comes from trusted `current_record`, not caller input, request/query data, or metadata. The operation requires authenticated actor, trusted tenant, exact current record, and Laravel Gate authorization checking actor/tenant and record/tenant membership.

The negative proof assigns a persisted Tenant B order directly to the record-aware page while trusted actor/tenant remain Tenant A. This bypasses normal resource-query scoping on purpose. Authorization still fails before executor mutation.

## `orders.refund_selected`

The bulk operation accepts only business intent:

```json
{
  "reason": "customer-request"
}
```

Target records come from the exact Filament `current_selection` snapshot. Applied filters are exposed independently through:

```text
filament/active_filters
```

Caller metadata such as fake `orderIds`, `tenantId`, or filter values is intentionally non-authoritative.

## Atomic tenant authorization

Bulk authorization is all-or-nothing. If one selected record does not belong to the trusted tenant, the whole operation is denied. The implementation does not silently remove unauthorized records and continue with an authorized subset.

A test-only host-query misconfiguration deliberately permits a mixed `[101, 201]` selection while trusted tenant authority remains Tenant A. Laravel Gate still rejects the invocation before confirmation or execution.

An actor whose authentication identifier is `null` is treated as **absent trusted actor context**, matching the production `AuthenticatedActorResolver` contract. The refund Gate also rejects a null identifier as defense-in-depth.

## Confirmation is approval-only

The refund flow preserves T-504:

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

Approval never calls the order executor and never redispatches the business action. Selection, tenant, or applied-filter drift produces a new confirmation requirement without spending the exact valid receipt.

### Why `refundSelected()` accepts only `reason`

The exposed Filament/Livewire method is intentionally:

```text
ListOrders::refundSelected(reason)
```

It is an **initial-invocation-only convergence seam**. It proves that human interaction and an agent-created Livewire binding reach the same page method and then the same `FilamentActionGateway → ActionBus` operation.

It deliberately does **not** accept:

```text
confirmationReceipt
idempotencyKey
orderIds
tenantId
```

`confirmationReceipt` and `idempotencyKey` belong to the invocation envelope, not Action input and not the Livewire driver call plan. Promoting an opaque confirmation capability into an exposed page-method argument would weaken the D-040/D-051 separation.

The exact approved retry is therefore proven separately at the production gateway seam in `FilamentMultiTenantOrderOperationsDemoTest`: challenge issuance → approval → explicit retry with the opaque receipt → one execution → completed replay, including tenant/selection/filter drift and non-spending mismatch behavior.

## Idempotency

`orders.refund_selected` uses the existing required-key idempotency pipeline.

The gateway-level executable proof covers:

- exact approved retry executes the refund side effect once;
- a completed lost-response retry replays without a second executor call or second confirmation;
- changed validated input conflicts;
- changed selection or applied-filter authority changes intent and cannot replay prior output;
- tenant changes use the trusted authority partition.

A CodeRabbit review questioned whether the exposed convenience adapter's constructor-supplied key would make a second independent intent conflict. A dedicated reproduction test was added before modifying that adapter. Both independent **unconfirmed** intents reached `confirmation_required` on the original code.

That result matches pipeline ordering: the required key is preflighted before confirmation, but a fresh idempotency claim is created only after confirmation immediately before execution. Because the exposed page adapter is initial-invocation-only, it does not claim the key. Completed-key replay/conflict semantics remain gateway-envelope tests.

Raw idempotency keys remain invocation-envelope candidates; they are not Action input or target authority.

## Human/agent convergence

The record and list pages expose:

```text
EditOrder::holdCurrent(reason)
ListOrders::refundSelected(reason)
```

`#[ExposeAction]`, `LivewireActionExposureReader`, `LivewireBindingProducer`, and `MethodLivewireComponentIdentityResolver` produce normal `driver=livewire` bindings. There is no `filament` RuntimeBinding driver.

Page methods delegate through `OrderDemoPageActions`; they contain no direct Eloquent mutation, `ActionBus`, or `ActionCall` shortcut. The non-consequential hold method is executed through the same page-method → gateway → ActionBus → executor seam.

The Testbench fixture does not configure a full Filament panel container. Therefore method execution boots the Filament page directly instead of rendering it through `Livewire::test()`. Binding production itself is tested with the production Livewire binding producer. A full application may add panel-level browser/render coverage without changing this authority model.

## Durable audit secrecy

The demo uses the existing T-404 migration plus `DatabaseAuditEventStore`; it does not define a second audit schema.

Adversarial markers are placed into tenant, record status, refund reason, applied filter, raw idempotency key, and exact confirmation receipt. Persisted audit rows are inspected directly and must contain none of those raw marker bytes.

The provider/provenance manifest remains allowlisted, including facts such as:

```text
order_demo.actor
order_demo.tenant
filament.current_record
filament.current_selection
filament.active_filters
surfacerelay.confirmation
```

The confirmed retry records `human_confirmation_present=true` without persisting the bearer receipt. Demo correlation IDs are internal sequence identifiers, not target/business/idempotency-derived values.

## Run the executable proof

From the repository root:

```bash
cd packages/laravel
composer test -- --filter 'Filament(MultiTenantOrderOperationsDemo|OrderDemoLivewireBinding|OrderDemoReviewHardening)Test'
```

The repository validation workflow additionally exercises PHP 8.3/8.4 across Illuminate 12/13, MySQL 8.4, Composer validation, PHP lint, the frozen contract validator, browser-runtime typecheck, and browser-runtime tests.

## What this is not

T-505 does not add:

- a generic multi-tenancy package;
- a Filament RuntimeBinding driver;
- a new ActionBus execution path;
- agent-only order endpoints;
- caller-authoritative order IDs, tenant IDs, selected IDs, filters, confirmation booleans, or challenge IDs;
- confirmation receipts or idempotency keys as exposed page-method business arguments;
- a new confirmation/idempotency/audit state machine;
- production order-domain code;
- changes to `spec/0.1/**` or browser-runtime production code.

The fixture is a reference vertical showing how an application can compose existing SurfaceRelay primitives while keeping target, authorization, and runtime capability authority server-side.