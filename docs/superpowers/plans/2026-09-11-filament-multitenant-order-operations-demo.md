# Filament Multi-Tenant Order Operations Demo Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement T-505 as an executable Filament multi-tenant order reference vertical that proves trusted tenant/current-record/current-selection/applied-filter context, confirmation, idempotency, audit, and Livewire exposure compose without caller-controlled target authority.

**Architecture:** Keep all SurfaceRelay production runtimes unchanged. Build a dedicated Testbench/Filament order fixture with two tenants and two order operations, route every invocation through the existing `FilamentActionGateway -> ActionBus` pipeline, use real Laravel Gate authorization for tenant membership, and reuse the existing confirmation/idempotency/audit implementations. The vertical must remain a proof of existing contracts; if implementation reveals a missing production primitive, stop and reopen the design gate instead of silently adding runtime behavior.

**Tech Stack:** PHP 8.3/8.4, Illuminate 12/13, Livewire 4.4, Filament 5.x, PHPUnit 11, Orchestra Testbench 10/11, Eloquent + SQLite for focused integration tests, existing MySQL 8.4 CI service coverage, existing browser runtime TypeScript/Vitest regression suite.

**Spec:** `docs/superpowers/specs/2026-09-11-filament-multitenant-order-operations-demo-design.md`

## Global Constraints

- Base: `main@b5da05b4a975ff8b2779960ea94e0c786a9c01db`.
- Branch: `feat/filament-order-operations-demo`.
- D-052 remains `PROPOSED` until the executable vertical and negative proofs are fully verified; promote it to `ACCEPTED` only in the final verified tracking checkpoint.
- T-505 is a reference vertical, not a new protocol, RuntimeBinding driver, transport, endpoint, tenancy framework, or order-domain package.
- `spec/0.1/**`, `packages/browser-runtime/src/**`, `packages/laravel/src/Livewire/**`, and the existing T-401 through T-504 runtime mechanisms are production-read-only for T-505.
- No production file under `packages/laravel/src/**` is expected to change. If a production primitive appears necessary, stop before coding it and reopen the T-505 design gate.
- Filament stays optional/dev-only; do not move `filament/filament` or `livewire/livewire` out of `require-dev`.
- `orders.hold_current` is exactly version 1, `page_scoped`, `reversible_write`, `moderate`, `idempotency=none`, with trusted requirements `authenticated_actor`, `tenant`, `current_record`.
- `orders.refund_selected` is exactly version 1, `page_scoped`, `external_side_effect`, `consequential`, `idempotency=required_key`, with trusted requirements `authenticated_actor`, `tenant`, `current_selection`, `human_confirmation`, plus explicit `FilamentContextExposure::activeFilters()`.
- Action input carries business intent only. Neither Action Definition may accept `tenantId`, order IDs, record IDs, selection IDs, filter state, confirmation flags/challenge IDs, binding IDs, or idempotency keys as business input.
- Filament resource/query tenant scoping is defense-in-depth only. Mutation authorization must independently compare the exact trusted record/selection against the exact trusted tenant before execution.
- Mixed-tenant selection rejects the entire invocation before execution; never partially apply to an authorized subset.
- Caller input, generic metadata, request/query/route values, raw Livewire selection/filter properties, WebMCP arguments, binding IDs, receipt candidates, or idempotency-key contents never create tenant/record/selection/filter/confirmation authority.
- T-504 remains approval-only. Filament modal approval must produce zero order mutation and zero refund side effect; the requesting caller explicitly retries through the normal gateway with the receipt candidate.
- Wrong-scope retry for changed tenant, selection, current record, applied filters, binding, surface, actor, or validated input must not execute and must not spend an otherwise-valid exact-scope receipt.
- `orders.refund_selected` exact completed retry with the same active idempotency key and same intent replays without a second application/external-side-effect execution.
- Structured audit must retain only the existing allowlisted semantic/provenance manifest. Never persist tenant values, order attributes/IDs as payload, selected-ID lists, filter values, refund reason, confirmation tokens, scope fingerprints, or raw idempotency keys.
- Human/agent convergence is proved by exact `#[ExposeAction]` methods and real `LivewireBindingProducer` output pointing to those same methods. T-505 does not add an agent-only business endpoint.
- Use TDD for each implementation checkpoint: focused failing test -> verify RED -> minimal fixture/implementation -> verify GREEN -> commit.
- Preserve the existing PHP 8.3/8.4 x Illuminate 12/13 x Testbench 10/11 CI matrix, MySQL 8.4 service coverage, browser typecheck/Vitest coverage, and `python scripts/validate.py` contract validation.

---

## File Structure

### Order-demo fixture domain

- `packages/laravel/tests/Fixtures/Filament/OrderDemo/Order.php` — persisted Eloquent order with stable primary key, tenant key, status, and held/refunded state used only by T-505 tests.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoTenantContext.php` — mutable test-host active-tenant source; the Filament resource scope and trusted `TenantResolver` read the same host authority.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoActorContext.php` — mutable test-host authenticated actor source used by the trusted actor resolver.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoActorResolver.php` — `AuthenticatedActorResolver` adapter returning a stable non-secret actor scope key.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoTenantResolver.php` — `TenantResolver` adapter returning the active tenant object with a stable non-secret tenant scope key.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderResource.php` — real Filament resource; ordinary table query is tenant-scoped and the table exposes an applied `status` filter.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/ListOrders.php` — real `ListRecords` page hosting selection/filter context and `refundSelected` exposure.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/EditOrder.php` — real record-aware page hosting `current_record` and `holdCurrent` exposure.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoExecutor.php` — application-side executor for the two exact action IDs; tracks refund execution count so idempotent replay can be proved.
- `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoPageActions.php` — thin fixture application adapter used by the exposed page methods for the non-consequential human/agent convergence proof; delegates to the production `FilamentActionGateway` and contains no direct mutation.
- `packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php` — minimal valid Filament page/modal view when a custom view is needed by the record page.

### T-505 test support

- `packages/laravel/tests/Support/FilamentOrderDemoHarness.php` — creates the exact two Action Definitions, validation rules, Laravel Gate authorization rules, production pipeline stages, confirmation service, idempotency service, structured auditor, `FilamentActionGateway`, and deterministic test helpers. Reuse existing `FilamentConfirmationMemoryStore`, `FilamentConfirmationMutableClock`, `FilamentConfirmationSequenceTokenGenerator`, `FilamentConfirmationIdempotencyStore`, and `FilamentConfirmationIdempotencyClock`; do not fork their semantics.

### Tests and walkthrough

- `packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php` — coherent end-to-end T-505 proof covering record, selection, filters, authorization, confirmation, idempotency, audit secrecy, and caller spoofing.
- `packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php` — real Livewire exposure/binding proof that exact order-page methods produce existing `livewire` RuntimeBindings with server-issued call plans and no second Filament driver.
- `examples/filament-orders/README.md` — concise walkthrough pointing to the executable fixture/tests; it must not claim a separately deployable demo application.

### Tracking modified only after verified implementation

- `docs/DECISION-REGISTER.md` — promote D-052 from `PROPOSED` to `ACCEPTED` only after all required proofs pass.
- `TASKS.md` — record T-505 acceptance/evidence only after verification.
- `STATUS.md` — record exact verified head, files, limitations, and next boundary.
- `REVIEW_REQUEST.md` — external-review handoff after the feature head is fully green.

---

### Task 1: Record-page tenant boundary and `orders.hold_current`

**Files:**
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/Order.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoTenantContext.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoActorContext.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoActorResolver.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoTenantResolver.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/EditOrder.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoExecutor.php`
- Create: `packages/laravel/tests/Support/FilamentOrderDemoHarness.php`
- Create: `packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php`

**Interfaces:**
- Consumes: existing `FilamentActionGateway`, `TrustedContextComposer`, `LaravelGateActionAuthorizer`, `LaravelAuthorizationRule`, `AuthorizationStage`, `LaravelInputValidationStage`, `ActionExecutionStage`, `OutputPolicyStage`, and the existing structured auditor port.
- Produces: `OrderDemoTenantContext::set(string $tenantId): void` and `OrderDemoTenantContext::current(): string`.
- Produces: `OrderDemoActorContext::set(GenericUser $actor): void` and `OrderDemoActorContext::current(): GenericUser`.
- Produces: `OrderDemoExecutor::execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed` with public `int $refundExecutions` for later idempotency assertions.
- Produces: `FilamentOrderDemoHarness::dispatchHold(EditOrder $page, string $reason, array $metadata = []): ActionPipelineOutcome`.

- [ ] **Step 1: Write the initial RED tests for the record operation**

Create the integration test with an in-memory SQLite `orders` table:

```php
$this->app['db']->connection()->getSchemaBuilder()->create('filament_order_demo_orders', static function (Blueprint $table): void {
    $table->integer('id')->primary();
    $table->string('tenant_id');
    $table->string('status');
    $table->boolean('held')->default(false);
    $table->boolean('refunded')->default(false);
});
```

Seed exactly:

```php
Order::query()->insert([
    ['id' => 101, 'tenant_id' => 'tenant-a', 'status' => 'paid',    'held' => false, 'refunded' => false],
    ['id' => 102, 'tenant_id' => 'tenant-a', 'status' => 'paid',    'held' => false, 'refunded' => false],
    ['id' => 103, 'tenant_id' => 'tenant-a', 'status' => 'pending', 'held' => false, 'refunded' => false],
    ['id' => 201, 'tenant_id' => 'tenant-b', 'status' => 'paid',    'held' => false, 'refunded' => false],
    ['id' => 202, 'tenant_id' => 'tenant-b', 'status' => 'pending', 'held' => false, 'refunded' => false],
]);
```

Add three tests:

```php
public function test_tenant_a_can_hold_exact_current_tenant_a_order(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->editPage(Order::query()->findOrFail(101));

    $outcome = $harness->dispatchHold($page, 'manual-review');

    self::assertTrue($outcome->completed);
    self::assertTrue((bool) Order::query()->findOrFail(101)->held);
    self::assertFalse((bool) Order::query()->findOrFail(201)->held);
}

public function test_input_and_metadata_cannot_retarget_current_record(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->editPage(Order::query()->findOrFail(101));

    $outcome = $harness->dispatchHold(
        $page,
        'manual-review',
        metadata: ['tenantId' => 'tenant-b', 'orderId' => 201, 'current_record' => 201],
    );

    self::assertTrue($outcome->completed);
    self::assertTrue((bool) Order::query()->findOrFail(101)->held);
    self::assertFalse((bool) Order::query()->findOrFail(201)->held);
}

public function test_cross_tenant_current_record_is_denied_before_execution(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->editPage(Order::query()->findOrFail(201));

    $outcome = $harness->dispatchHold($page, 'manual-review');

    self::assertFalse($outcome->completed);
    self::assertSame('authorization_denied', $outcome->halt?->code);
    self::assertFalse((bool) Order::query()->findOrFail(201)->held);
}
```

The cross-tenant test deliberately assigns an exact persisted Tenant B record to the trusted record-aware page. This bypasses normal resource query scoping in the harness and proves authorization is an independent boundary.

- [ ] **Step 2: Verify RED**

```bash
cd packages/laravel
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(tenant_a_can_hold|input_and_metadata|cross_tenant_current_record)'
```

Expected: FAIL because the T-505 order fixture/harness does not exist.

- [ ] **Step 3: Add the order model and host tenant/actor sources**

`Order.php`:

```php
final class Order extends Model
{
    protected $table = 'filament_order_demo_orders';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['held' => 'boolean', 'refunded' => 'boolean'];
}
```

`OrderDemoTenantContext` holds only a test-host current tenant string and throws `RuntimeException('Order demo tenant is not set.')` when absent.

`OrderDemoActorContext` holds one `Illuminate\Auth\GenericUser` and throws `RuntimeException('Order demo actor is not set.')` when absent.

Resolvers return exact trusted entries through `ResolvedTrustedValue`:

```php
return new ResolvedTrustedValue(
    value: $tenantId,
    provenance: new ContextProvenance('order_demo.tenant'),
    confirmationScopeKey: 'order-demo-tenant:' . $tenantId,
);
```

and:

```php
return new ResolvedTrustedValue(
    value: $actor,
    provenance: new ContextProvenance('order_demo.actor'),
    confirmationScopeKey: 'order-demo-actor:' . (string) $actor->getAuthIdentifier(),
);
```

Do not read route/request/session/global tenant IDs in these resolvers.

- [ ] **Step 4: Define `orders.hold_current` exactly in the harness**

Register:

```php
new ActionDefinition(
    id: 'orders.hold_current',
    version: 1,
    title: 'Hold current order',
    description: 'Place the exact trusted current order on hold.',
    inputSchema: [
        'type' => 'object',
        'properties' => ['reason' => ['type' => 'string', 'minLength' => 1]],
        'required' => ['reason'],
        'additionalProperties' => false,
    ],
    scope: ActionScope::PageScoped,
    effect: ActionEffect::ReversibleWrite,
    risk: ActionRisk::Moderate,
    idempotency: IdempotencyPolicy::None,
    outputSensitivity: OutputSensitivity::Normal,
    outputContentTrust: OutputContentTrust::TrustedApplicationData,
    contextRequirements: [
        ContextRequirement::AuthenticatedActor,
        ContextRequirement::Tenant,
        ContextRequirement::CurrentRecord,
    ],
)
```

Register the Laravel validation rule for `reason` as `required|string|min:1` through the existing `InMemoryActionValidationRules`.

- [ ] **Step 5: Add exact Laravel Gate authorization**

Configure a `LaravelAuthorizationRule` whose argument resolver uses only validated input + trusted context:

```php
new LaravelAuthorizationRule(
    ability: 'order-demo.hold',
    arguments: static fn (array $input, InvocationContext $context): array => [
        $context->require(ContextRequirement::CurrentRecord)->value,
        $context->require(ContextRequirement::Tenant)->value,
    ],
)
```

Define the Gate closure so both actor permission and tenant membership are checked before execution:

```php
$gate->define('order-demo.hold', static function (GenericUser $actor, Order $order, string $tenantId): bool {
    return $actor->getAuthIdentifier() !== null
        && ($actor->tenant_id ?? null) === $tenantId
        && $order->tenant_id === $tenantId
        && ($actor->can_hold ?? false) === true;
});
```

Do not use ambient `Auth::user()` or re-query an order ID from input.

- [ ] **Step 6: Implement the minimal application executor**

For `orders.hold_current`:

```php
$order = $context->require(ContextRequirement::CurrentRecord)->value;
if (!$order instanceof Order) {
    throw new RuntimeException('Order demo current record is invalid.');
}

$order->held = true;
$order->save();

return ['orderId' => $order->getKey(), 'held' => true];
```

The executor does not accept or resolve an order ID from `$input`.

- [ ] **Step 7: Build the real production pipeline in `FilamentOrderDemoHarness`**

The hold path must use:

```text
LaravelInputValidationStage
AuthorizationStage(LaravelGateActionAuthorizer)
IdempotencyStage(existing service; policy=none produces bypass)
ConfirmationStage(existing service; moderate hold does not require it)
ActionExecutionStage(OrderDemoExecutor, same IdempotencyService)
OutputPolicyStage
StructuredActionPipelineAuditor
```

Construct `FilamentActionGateway` with a `TrustedContextComposer` backed by the exact actor/tenant resolvers. Do not add a test-only ActionBus shortcut that omits authorization.

`dispatchHold()` must call the production gateway with:

```php
return $this->gateway->dispatch(
    page: $page,
    actionId: 'orders.hold_current',
    actionVersion: 1,
    input: ['reason' => $reason],
    surface: 'filament',
    correlationId: 'order-demo-hold-' . $page->record->getKey(),
    bindingId: 'order-demo-hold-binding',
    metadata: $metadata,
);
```

- [ ] **Step 8: Verify GREEN**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(tenant_a_can_hold|input_and_metadata|cross_tenant_current_record)'
```

Expected: all three tests PASS; Tenant B order never mutates in spoof/cross-tenant cases.

- [ ] **Step 9: Commit**

```bash
git add packages/laravel/tests/Fixtures/Filament/OrderDemo packages/laravel/tests/Support/FilamentOrderDemoHarness.php packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
git commit -m "test(filament): add tenant-safe current-order demo"
```

---

### Task 2: Bulk selection, applied filters, and atomic tenant authorization

**Files:**
- Modify: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderResource.php`
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/ListOrders.php`
- Modify: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoExecutor.php`
- Modify: `packages/laravel/tests/Support/FilamentOrderDemoHarness.php`
- Modify: `packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php`

**Interfaces:**
- Produces: real tenant-scoped `OrderResource::getEloquentQuery()` plus a real `status` table filter.
- Produces: `FilamentOrderDemoHarness::dispatchRefund(ListOrders $page, string $reason, string $idempotencyKey, ?string $confirmationReceipt = null, array $metadata = []): ActionPipelineOutcome`.
- Consumes: T-502 `current_selection` and T-503 `FilamentContextExposure::activeFilters()` unchanged.

- [ ] **Step 1: Write RED selection/tenant/filter tests**

Add:

```php
public function test_resource_query_is_scoped_to_active_tenant_but_authorization_remains_independent(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->listPage();

    self::assertSame([101, 102, 103], $page->getTableQuery()->orderBy('id')->pluck('id')->all());
}
```

Add exact-selection proof with the first invocation expected to stop at confirmation, not execution:

```php
public function test_refund_uses_exact_trusted_selection_not_caller_ids(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->listPage();
    $page->selectedTableRecords = [101, 102];
    $this->applyStatusFilter($page, 'paid');

    $outcome = $harness->dispatchRefund(
        $page,
        reason: 'customer-request',
        idempotencyKey: 'refund-selection-A',
        metadata: ['orderIds' => [201], 'tenantId' => 'tenant-b', 'filters' => ['status' => 'pending']],
    );

    self::assertFalse($outcome->completed);
    self::assertSame('confirmation_required', $outcome->halt?->code);
    self::assertSame(0, $harness->executor->refundExecutions);

    $selection = $outcome->state->context->require(ContextRequirement::CurrentSelection)->value;
    self::assertSame([101, 102], array_map(static fn (Order $order): int => (int) $order->getKey(), $selection));
}
```

Add the defense-in-depth negative proof:

```php
public function test_mixed_tenant_trusted_selection_is_denied_atomically_before_confirmation_or_execution(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->listPage();
    $page->selectedTableRecords = [101, 201];

    $outcome = $harness->dispatchRefund($page, 'customer-request', 'mixed-tenant-key');

    self::assertFalse($outcome->completed);
    self::assertSame('authorization_denied', $outcome->halt?->code);
    self::assertSame(0, $harness->executor->refundExecutions);
    self::assertFalse((bool) Order::query()->findOrFail(101)->refunded);
    self::assertFalse((bool) Order::query()->findOrFail(201)->refunded);
}
```

The selected Tenant B record is introduced through the trusted test page state deliberately; the test must not rely on ordinary resource scoping to make the attack impossible.

- [ ] **Step 2: Verify RED**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(resource_query|refund_uses_exact|mixed_tenant)'
```

Expected: FAIL because list resource/refund definition/authorization do not exist yet.

- [ ] **Step 3: Implement the real tenant-scoped Filament resource**

`OrderResource::getEloquentQuery()` must start from the parent query and constrain it by the same host tenant context used by the trusted tenant resolver:

```php
public static function getEloquentQuery(): Builder
{
    $tenantId = app(OrderDemoTenantContext::class)->current();

    return parent::getEloquentQuery()->where('tenant_id', $tenantId);
}
```

Table definition uses a real status filter. Use Filament's public filter APIs only; do not read raw `tableFilters`/`tableDeferredFilters` in SurfaceRelay fixture code.

```php
SelectFilter::make('status')
    ->options(['paid' => 'Paid', 'pending' => 'Pending'])
```

The integration-test helper sets the public filter form and calls `applyTableFilters()` before invocation; later assertions read authority through the existing T-503 resolver/gateway path.

- [ ] **Step 4: Register `orders.refund_selected` exactly**

```php
new ActionDefinition(
    id: 'orders.refund_selected',
    version: 1,
    title: 'Refund selected orders',
    description: 'Refund the exact trusted Filament current selection.',
    inputSchema: [
        'type' => 'object',
        'properties' => ['reason' => ['type' => 'string', 'minLength' => 1]],
        'required' => ['reason'],
        'additionalProperties' => false,
    ],
    scope: ActionScope::PageScoped,
    effect: ActionEffect::ExternalSideEffect,
    risk: ActionRisk::Consequential,
    idempotency: IdempotencyPolicy::RequiredKey,
    outputSensitivity: OutputSensitivity::Normal,
    outputContentTrust: OutputContentTrust::TrustedApplicationData,
    contextRequirements: [
        ContextRequirement::AuthenticatedActor,
        ContextRequirement::Tenant,
        ContextRequirement::CurrentSelection,
        ContextRequirement::HumanConfirmation,
    ],
)
```

Validation remains only `reason => required|string|min:1`.

- [ ] **Step 5: Add all-or-nothing refund authorization**

Arguments resolver:

```php
new LaravelAuthorizationRule(
    ability: 'order-demo.refund-selected',
    arguments: static fn (array $input, InvocationContext $context): array => [
        $context->require(ContextRequirement::CurrentSelection)->value,
        $context->require(ContextRequirement::Tenant)->value,
    ],
)
```

Gate closure:

```php
$gate->define('order-demo.refund-selected', static function (GenericUser $actor, array $orders, string $tenantId): bool {
    if (($actor->tenant_id ?? null) !== $tenantId || ($actor->can_refund ?? false) !== true || $orders === []) {
        return false;
    }

    foreach ($orders as $order) {
        if (!$order instanceof Order || $order->tenant_id !== $tenantId) {
            return false;
        }
    }

    return true;
});
```

Do not filter unauthorized records out of `$orders`; one mismatch denies the whole call.

- [ ] **Step 6: Implement refund execution only after authorization/confirmation**

Executor branch:

```php
$orders = $context->require(ContextRequirement::CurrentSelection)->value;
$this->refundExecutions++;

foreach ($orders as $order) {
    if (!$order instanceof Order) {
        throw new RuntimeException('Order demo selection is invalid.');
    }

    $order->refunded = true;
    $order->save();
}

return [
    'refundedCount' => count($orders),
    'orderIds' => array_map(static fn (Order $order): int => (int) $order->getKey(), $orders),
];
```

This output is replay payload for the existing idempotency mechanism; it is not audit persistence data.

- [ ] **Step 7: Wire explicit applied-filter exposure in `dispatchRefund()`**

The production gateway call must contain:

```php
contextExposure: FilamentContextExposure::activeFilters(),
```

and pass `idempotencyKey` through the invocation context candidate, not through Action input.

- [ ] **Step 8: Verify GREEN**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(resource_query|refund_uses_exact|mixed_tenant)'
```

Expected: PASS; mixed-tenant selection halts at Authorization, before confirmation challenge issuance and before `refundExecutions` increments.

- [ ] **Step 9: Commit**

```bash
git add packages/laravel/tests/Fixtures/Filament/OrderDemo packages/laravel/tests/Support/FilamentOrderDemoHarness.php packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
git commit -m "test(filament): prove tenant-safe selected-order authority"
```

---

### Task 3: Confirmation scope drift and idempotent refund retry

**Files:**
- Modify: `packages/laravel/tests/Support/FilamentOrderDemoHarness.php`
- Modify: `packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php`
- Reuse unchanged: `packages/laravel/tests/Support/FilamentConfirmationMemoryStore.php`
- Reuse unchanged: `packages/laravel/tests/Support/FilamentConfirmationMutableClock.php`
- Reuse unchanged: `packages/laravel/tests/Support/FilamentConfirmationSequenceTokenGenerator.php`
- Reuse unchanged: `packages/laravel/tests/Support/FilamentConfirmationIdempotencyStore.php`
- Reuse unchanged: `packages/laravel/tests/Support/FilamentConfirmationIdempotencyClock.php`

**Interfaces:**
- Consumes: existing `ConfirmationService`, `ConfirmationScopeHasher`, `ConfirmationStage`, `IdempotencyService`, `IdempotencyStage`, and `ActionExecutionStage` exactly as production uses them.
- Produces deterministic `FilamentOrderDemoHarness::approve(ConfirmationChallenge $challenge): string` that calls only the existing confirmation service and returns the same approved opaque token for explicit retry.

- [ ] **Step 1: Write RED exact-retry and approval-only test**

```php
public function test_refund_requires_approval_then_exact_retry_executes_once_and_replays_once(): void
{
    $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
    $page = $this->listPage();
    $page->selectedTableRecords = [101, 102];
    $this->applyStatusFilter($page, 'paid');

    $first = $harness->dispatchRefund($page, 'customer-request', 'refund-K');
    $challenge = $this->assertConfirmationRequired($first);
    self::assertSame(0, $harness->executor->refundExecutions);

    $receipt = $harness->approve($challenge);
    self::assertSame(0, $harness->executor->refundExecutions, 'Approval must not execute refund code.');

    $second = $harness->dispatchRefund($page, 'customer-request', 'refund-K', $receipt);
    self::assertTrue($second->completed);
    self::assertSame(1, $harness->executor->refundExecutions);

    $replay = $harness->dispatchRefund($page, 'customer-request', 'refund-K');
    self::assertTrue($replay->completed);
    self::assertSame(1, $harness->executor->refundExecutions, 'Completed retry must replay without duplicate refund.');
}
```

- [ ] **Step 2: Write RED wrong-scope non-spending tests**

Add separate tests for selection, tenant, and applied-filter drift. Each follows this exact structure:

```text
issue challenge for state A
approve challenge A
change exactly one trusted dimension
retry with receipt A -> confirmation_required, zero execution
restore exact state A
retry with same receipt A -> completed
```

Selection case: `[101,102] -> [101,103] -> [101,102]`.

Tenant case: trusted tenant `tenant-a -> tenant-b -> tenant-a`; use a tenant-B-compatible page/selection during the wrong attempt so the test reaches confirmation-scope mismatch rather than being satisfied only by authorization denial. The exact restored retry must still consume the original receipt.

Filter case:

```php
$this->applyStatusFilter($page, 'paid');
$first = $harness->dispatchRefund(...);
$challenge = $this->assertConfirmationRequired($first);
$receipt = $harness->approve($challenge);

$page->getTableFiltersForm()->fill(['status' => ['value' => 'pending']]);
self::assertSame(
    'paid',
    $page->getTableFilterState('status')['value'] ?? null,
    'Deferred form edit is not applied authority yet.',
);

$page->applyTableFilters();
$wrong = $harness->dispatchRefund(..., confirmationReceipt: $receipt);
$this->assertConfirmationRequired($wrong);

$this->applyStatusFilter($page, 'paid');
$exact = $harness->dispatchRefund(..., confirmationReceipt: $receipt);
self::assertTrue($exact->completed);
```

If Filament's public SelectFilter state uses the equivalent supported key generated by Filament 5 rather than `value`, keep the test helper centralized and assert via `getTableFilterState('status')`; do not read `tableFilters` or `tableDeferredFilters` directly.

- [ ] **Step 3: Write RED idempotency conflict tests**

Same caller key with changed validated input must conflict after a completed record exists:

```php
$conflict = $harness->dispatchRefund(
    $page,
    reason: 'different-reason',
    idempotencyKey: 'refund-K',
);

self::assertFalse($conflict->completed);
self::assertSame('idempotency_conflict', $conflict->halt?->code);
self::assertSame(1, $harness->executor->refundExecutions);
```

Also prove tenant/selection/applied-filter differences change intent and therefore cannot replay the completed output under the same raw caller key.

- [ ] **Step 4: Verify RED**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(refund_requires|selection_drift|tenant_drift|applied_filter_drift|same_idempotency)'
```

Expected: failures until the harness wires the real confirmation/idempotency services across the complete pipeline.

- [ ] **Step 5: Reuse the T-504 deterministic services; do not create order-specific state machines**

`FilamentOrderDemoHarness` owns one authoritative confirmation service:

```php
$this->confirmationService = new ConfirmationService(
    $this->confirmationStore,
    $this->confirmationClock,
    $this->confirmationTokens,
);
```

Bind that exact service into the container when the Filament confirmation bridge is exercised:

```php
$this->app->instance(ConfirmationService::class, $this->confirmationService);
```

Use one existing test idempotency store/clock and one `IdempotencyService` instance for both `IdempotencyStage` and `ActionExecutionStage`.

- [ ] **Step 6: Implement `approve()` as approval only**

```php
public function approve(ConfirmationChallenge $challenge): string
{
    $receipt = $this->confirmationService->approveChallenge($challenge->challengeId);

    if ($receipt === null) {
        throw new RuntimeException('Order demo confirmation could not be approved.');
    }

    return $receipt;
}
```

No call to gateway, ActionBus, executor, or order mutation is allowed inside `approve()`.

- [ ] **Step 7: Verify GREEN**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_(refund_requires|selection_drift|tenant_drift|applied_filter_drift|same_idempotency)'
```

Expected: all pass; wrong-scope attempts leave the exact valid receipt usable after state restoration; exact completed retry leaves `refundExecutions === 1`.

- [ ] **Step 8: Run the entire order-demo test so Task 1/2 regressions are caught**

```bash
composer test -- --filter FilamentMultiTenantOrderOperationsDemoTest
```

Expected: all T-505 order scenarios green.

- [ ] **Step 9: Commit**

```bash
git add packages/laravel/tests/Support/FilamentOrderDemoHarness.php packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
git commit -m "test(filament): prove order confirmation and idempotency drift"
```

---

### Task 4: Livewire/Filament human-agent convergence on the same page methods

**Files:**
- Create: `packages/laravel/tests/Fixtures/Filament/OrderDemo/OrderDemoPageActions.php`
- Modify: `packages/laravel/tests/Fixtures/Filament/OrderDemo/EditOrder.php`
- Modify: `packages/laravel/tests/Fixtures/Filament/OrderDemo/ListOrders.php`
- Create: `packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php`
- Create: `packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php`
- Modify only if fixture wiring requires it: `packages/laravel/tests/Support/FilamentOrderDemoHarness.php`

**Interfaces:**
- Consumes: existing `#[ExposeAction]`, `LivewireActionExposureReader`, `LivewireBindingProducer`, `MethodLivewireComponentIdentityResolver`, and `RuntimeBinding` with `driver=livewire`.
- Produces exact public page methods `EditOrder::holdCurrent(string $reason): array` and `ListOrders::refundSelected(string $reason): array` with Action input-compatible signatures.
- `OrderDemoPageActions` contains delegation only; no direct database mutation and no alternate authorization path.

- [ ] **Step 1: Write RED exact exposure/binding tests**

Build an `InMemoryActionRegistry` containing the same two Action Definitions used by the harness and assert the production exposure reader returns exact identities/methods:

```php
$recordExposures = (new LivewireActionExposureReader($registry))->forComponent($editPage);
self::assertSame('orders.hold_current', $recordExposures[0]->definition->id);
self::assertSame('holdCurrent', $recordExposures[0]->method);

$tableExposures = (new LivewireActionExposureReader($registry))->forComponent($listPage);
self::assertSame('orders.refund_selected', $tableExposures[0]->definition->id);
self::assertSame('refundSelected', $tableExposures[0]->method);
```

Then use the real `LivewireBindingProducer` with a deterministic `BindingIdGenerator`:

```php
$producer = new LivewireBindingProducer(
    new LivewireActionExposureReader($registry),
    new MethodLivewireComponentIdentityResolver(),
    $bindingIds,
);
```

Assert both bindings:

```text
driver = livewire
lifecycle = component
exact action id/version
target.componentId = exact mounted page id
target.method = holdCurrent/refundSelected
target.inputOrder = [reason]
target.requiredCount = 1
```

There must be no `filament` driver and no target order/tenant ID in the binding target.

- [ ] **Step 2: Write RED shared-method execution proof for the non-consequential record action**

The record page receives `OrderDemoPageActions` through Livewire lifecycle injection exactly like the existing Prep List fixture:

```php
protected OrderDemoPageActions $orderDemoActions;

public function boot(OrderDemoPageActions $actions): void
{
    $this->orderDemoActions = $actions;
}

#[ExposeAction(id: 'orders.hold_current', version: 1)]
public function holdCurrent(string $reason): array
{
    return $this->orderDemoActions->holdCurrent($this, $reason);
}
```

The test invokes `holdCurrent('manual-review')` through the mounted Livewire page/component path and asserts Order 101 becomes held through the same gateway/ActionBus/executor used by Task 1. Do not place Eloquent mutation in the page method.

- [ ] **Step 3: Add refund exposure without turning runtime-envelope candidates into Action input**

`ListOrders` declares:

```php
#[ExposeAction(id: 'orders.refund_selected', version: 1)]
public function refundSelected(string $reason): array
```

The method delegates only to `OrderDemoPageActions`. The binding schema remains exactly one business field, `reason`.

For T-505, the Livewire binding test proves exact exposure/binding production for this consequential method; the explicit confirmation-receipt/idempotency-envelope retry itself remains covered by Task 3 through the production `FilamentActionGateway`. Do **not** add `confirmationReceipt`, `idempotencyKey`, `orderIds`, or `tenantId` to the method signature merely to make the test harness convenient, because that would incorrectly promote invocation-envelope candidates into Action input/call-plan authority.

- [ ] **Step 4: Implement `OrderDemoPageActions` as a thin adapter**

`holdCurrent()` delegates directly to the production gateway and requires completion:

```php
public function holdCurrent(EditOrder $page, string $reason): array
{
    $outcome = $this->gateway->dispatch(
        page: $page,
        actionId: 'orders.hold_current',
        actionVersion: 1,
        input: ['reason' => $reason],
        surface: 'filament',
        correlationId: 'order-demo-livewire-hold',
        bindingId: 'order-demo-livewire-hold-binding',
    );

    if (!$outcome->completed) {
        throw new RuntimeException('Order demo hold action did not complete.');
    }

    return $outcome->state->output;
}
```

For `refundSelected()`, the fixture adapter may perform only the initial invocation needed to demonstrate the same method reaches the real gateway; use a deterministic server-side test idempotency candidate supplied when the fixture service is constructed, and normalize `confirmation_required` to a non-secret result such as:

```php
return ['status' => 'confirmation_required'];
```

Do not expose the challenge token from this convenience adapter and do not auto-approve or auto-retry. Exact receipt handling remains the Task 3 gateway proof.

- [ ] **Step 5: Verify GREEN**

```bash
composer test -- --filter FilamentOrderDemoLivewireBindingTest
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_tenant_a_can_hold_exact_current_tenant_a_order'
```

Expected: bindings use the existing `livewire` driver and exact page methods; the record method executes through the same ActionBus path.

- [ ] **Step 6: Add a static source guard for the fixture page methods**

In `FilamentOrderDemoLivewireBindingTest`, read `EditOrder.php` and `ListOrders.php` source and assert the page classes do not contain direct mutation/query shortcuts such as:

```text
->update(
->delete(
::query(
ActionBus
ActionCall
```

The permitted dependency is `OrderDemoPageActions` + `#[ExposeAction]` only. This keeps the demo honest about human/agent convergence instead of hiding a second business path in the page.

- [ ] **Step 7: Commit**

```bash
git add packages/laravel/tests/Fixtures/Filament/OrderDemo packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php packages/laravel/tests/Support/FilamentOrderDemoHarness.php
git commit -m "test(filament): prove shared order Livewire bindings"
```

---

### Task 5: Durable audit secrecy, walkthrough, full verification, and review checkpoint

**Files:**
- Modify: `packages/laravel/tests/Support/FilamentOrderDemoHarness.php`
- Modify: `packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php`
- Create: `examples/filament-orders/README.md`
- Modify after tests pass: `docs/DECISION-REGISTER.md`
- Modify after tests pass: `TASKS.md`
- Modify after tests pass: `STATUS.md`
- Modify after tests pass: `REVIEW_REQUEST.md`

**Interfaces:**
- Consumes unchanged `AuditEventFactory`, `StructuredActionPipelineAuditor`, `DatabaseAuditEventStore`, and `0000_00_00_000001_create_surfacerelay_audit_events.php` migration.
- Produces no new runtime API.

- [ ] **Step 1: Write RED durable audit minimization test**

Run the existing audit migration against the T-505 SQLite connection, execute one successful hold and one successful confirmed refund, then inspect persisted rows.

Use adversarial marker values:

```text
TENANT-A-SECRET-MARKER
ORDER-101-SECRET-MARKER
REFUND-REASON-SECRET-MARKER
FILTER-SECRET-MARKER
IDEMPOTENCY-SECRET-MARKER
```

Project every persisted row to JSON and assert all markers and the exact confirmation receipt are absent:

```php
$rows = $this->app['db']->table('surfacerelay_audit_events')->orderBy('recorded_at')->get();
$json = json_encode($rows->map(static fn ($row): array => (array) $row)->all(), JSON_THROW_ON_ERROR);

foreach ($forbiddenMarkers as $marker) {
    self::assertStringNotContainsString($marker, $json);
}
```

Assert the trusted manifest does contain provider-only facts:

```text
order_demo.actor
order_demo.tenant
filament.current_record or filament.current_selection
filament.active_filters for refund
surfacerelay.confirmation presence represented only by the existing boolean/manifest semantics
```

Do not weaken the existing D-047 schema to accommodate the demo.

- [ ] **Step 2: Verify RED if the harness is still using a no-op auditor**

```bash
composer test -- --filter 'FilamentMultiTenantOrderOperationsDemoTest::test_structured_audit'
```

Expected: FAIL until the harness uses the real structured auditor/database store.

- [ ] **Step 3: Wire the real structured audit persistence**

Use:

```php
new StructuredActionPipelineAuditor(
    new AuditEventFactory($auditClock),
    new DatabaseAuditEventStore($this->app['db']->connection()),
)
```

Run the existing audit migration in test setup; do not create an order-demo-specific audit table.

- [ ] **Step 4: Verify audit GREEN plus complete T-505 suite**

```bash
composer test -- --filter FilamentMultiTenantOrderOperationsDemoTest
composer test -- --filter FilamentOrderDemoLivewireBindingTest
```

Expected: all green, with persisted rows containing no forbidden business/trusted/capability material.

- [ ] **Step 5: Write the executable walkthrough**

Create `examples/filament-orders/README.md` with these sections only:

```text
# Filament Multi-Tenant Order Operations Demo
What this proves
Domain fixture
orders.hold_current
orders.refund_selected
Trust boundaries
Run the executable proof
Deliberate non-goals
```

The run command is:

```bash
cd packages/laravel
composer test -- --filter 'Filament(MultiTenantOrderOperationsDemo|OrderDemoLivewireBinding)Test'
```

State explicitly that this directory is documentation for an executable package fixture, not a standalone/deployable Laravel application.

- [ ] **Step 6: Run focused PHP verification**

```bash
cd packages/laravel
composer test -- --filter 'Filament(MultiTenantOrderOperationsDemo|OrderDemoLivewireBinding)Test'
composer validate --strict
```

Expected: all focused tests green and Composer metadata valid.

- [ ] **Step 7: Run full PHP regression**

```bash
composer test
```

Expected: full PHP suite green on the local dependency set.

- [ ] **Step 8: Run PHP syntax validation**

From repository root:

```bash
find packages/laravel -name '*.php' -not -path '*/vendor/*' -print0 | xargs -0 -n1 php -l
```

Expected: no syntax errors.

- [ ] **Step 9: Run frozen contract validation**

```bash
python scripts/validate.py
```

Expected: green with frozen `spec/0.1/**` unchanged.

- [ ] **Step 10: Run browser isolation regression**

```bash
cd packages/browser-runtime
npm ci
npm run typecheck
npm test
```

Expected: typecheck green and the existing Vitest suite green. No browser-runtime production file should be changed by T-505.

- [ ] **Step 11: Review the full diff for forbidden production drift**

From repository root:

```bash
git diff --name-only main...HEAD
```

Expected implementation paths are limited to:

```text
packages/laravel/tests/Fixtures/Filament/OrderDemo/**
packages/laravel/tests/Fixtures/views/filament-order-demo-page.blade.php
packages/laravel/tests/Support/FilamentOrderDemoHarness.php
packages/laravel/tests/Integration/FilamentMultiTenantOrderOperationsDemoTest.php
packages/laravel/tests/Integration/FilamentOrderDemoLivewireBindingTest.php
examples/filament-orders/README.md
docs/superpowers/specs/2026-09-11-filament-multitenant-order-operations-demo-design.md
docs/superpowers/plans/2026-09-11-filament-multitenant-order-operations-demo.md
docs/DECISION-REGISTER.md
TASKS.md
STATUS.md
REVIEW_REQUEST.md
```

If any `packages/laravel/src/**`, `packages/browser-runtime/src/**`, or `spec/0.1/**` implementation file appears, stop and justify/reopen the design gate before treating T-505 as complete.

- [ ] **Step 12: Promote D-052 only after all executable proofs pass**

Change only its status:

```text
D-052 | PROPOSED -> ACCEPTED
```

The decision text remains semantically unchanged.

- [ ] **Step 13: Update task/status/review evidence**

`TASKS.md` must record the final T-505 outcome and exact acceptance proofs.

`STATUS.md` must record:

```text
T-505 status
base
exact feature head
PHP test/assertion totals
browser typecheck/Vitest totals
contract validation
known limitations
next boundary
```

`REVIEW_REQUEST.md` must identify T-505 scope, exact base/head, changed files, security/trust invariants, verification commands/results, and explicitly state that merge remains a separate gate.

- [ ] **Step 14: Commit final implementation checkpoint**

```bash
git add examples/filament-orders/README.md docs/DECISION-REGISTER.md TASKS.md STATUS.md REVIEW_REQUEST.md packages/laravel/tests
git commit -m "docs(filament): complete multi-tenant order demo proof"
```

- [ ] **Step 15: Push the exact feature head and require full CI before external review**

```bash
git push -u origin feat/filament-order-operations-demo
```

Expected GitHub Actions `validate` workflow: all jobs green, including the four PHP/Illuminate matrix jobs, contract, PHP lint, and browser job. Record the exact run IDs in `STATUS.md`/`REVIEW_REQUEST.md` only after they actually pass.

---

## Plan Self-Review Checklist

Before implementation begins, the executor must preserve these mappings:

- Record authority -> Task 1.
- Tenant-membership authorization independent of query scope -> Tasks 1 and 2.
- Caller target spoofing -> Tasks 1 and 2.
- Exact selection + mixed-tenant atomic denial -> Task 2.
- Applied-filter authority independent from selection -> Tasks 2 and 3.
- Confirmation approval-only semantics -> Task 3.
- Selection/tenant/filter scope drift + non-spending receipt -> Task 3.
- Required-key idempotency + completed replay + changed-intent conflict -> Task 3.
- Existing Livewire driver / exact exposed page methods / no Filament driver -> Task 4.
- No business mutation in page methods -> Task 4 source guard.
- Structured audit payload minimization -> Task 5.
- No production runtime/spec/browser drift -> Task 5 full-diff gate.
- D-052 acceptance only after executable evidence -> Task 5.

No task in this plan authorizes a production runtime change. Any discovered need for one is a design-gate stop condition, not an implementation convenience.