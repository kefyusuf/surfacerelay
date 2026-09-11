<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Filament\Context\FilamentActiveFilterContextResolver;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\EditOrder;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\ListOrders;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;
use SurfaceRelay\Laravel\Tests\Support\FilamentOrderDemoHarness;

final class FilamentMultiTenantOrderOperationsDemoTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', '0123456789abcdef0123456789abcdef');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['db']->connection()->getSchemaBuilder()->create(
            'filament_order_demo_orders',
            static function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('tenant_id');
                $table->string('status');
                $table->boolean('held')->default(false);
                $table->boolean('refunded')->default(false);
            },
        );

        Order::query()->insert([
            ['id' => 101, 'tenant_id' => 'tenant-a', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 102, 'tenant_id' => 'tenant-a', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 103, 'tenant_id' => 'tenant-a', 'status' => 'pending', 'held' => false, 'refunded' => false],
            ['id' => 201, 'tenant_id' => 'tenant-b', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 202, 'tenant_id' => 'tenant-b', 'status' => 'pending', 'held' => false, 'refunded' => false],
        ]);
    }

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
            metadata: [
                'tenantId' => 'tenant-b',
                'orderId' => 201,
                'current_record' => 201,
            ],
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

    public function test_resource_query_is_scoped_to_active_tenant_but_authorization_remains_independent(): void
    {
        $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $page = $this->listPage();

        self::assertSame(
            [101, 102, 103],
            $page->getTable()->getQuery()->orderBy('id')->pluck('id')->all(),
        );
    }

    public function test_refund_uses_exact_trusted_selection_not_caller_ids(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $page = $this->refundPage([101, 102], 'paid');

        $outcome = $harness->dispatchRefund(
            $page,
            reason: 'customer-request',
            idempotencyKey: 'refund-selection-A',
            metadata: [
                'orderIds' => [201],
                'tenantId' => 'tenant-b',
                'filters' => ['status' => 'pending'],
            ],
        );

        self::assertFalse($outcome->completed);
        self::assertSame('confirmation_required', $outcome->halt?->code);
        self::assertSame(0, $harness->executor->refundExecutions);

        $selection = $outcome->state->context->require(ContextRequirement::CurrentSelection)->value;
        self::assertSame(
            [101, 102],
            array_map(static fn (Order $order): int => (int) $order->getKey(), $selection),
        );

        $activeFilters = $outcome->state->context
            ->requireTrustedExtension(FilamentActiveFilterContextResolver::EXTENSION_KEY)
            ->value;
        self::assertSame(
            ['status' => ['value' => 'paid']],
            $activeFilters,
            'Caller metadata must not replace the exact applied Filament filter authority.',
        );
    }

    public function test_mixed_tenant_trusted_selection_is_denied_atomically_before_confirmation_or_execution(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $harness->simulateUnscopedHostQuery();
        $page = $this->refundPage([101, 201]);

        $outcome = $harness->dispatchRefund(
            $page,
            reason: 'customer-request',
            idempotencyKey: 'mixed-tenant-key',
        );

        self::assertFalse($outcome->completed);
        self::assertSame('authorization_denied', $outcome->halt?->code);
        self::assertSame(0, $harness->executor->refundExecutions);
        self::assertFalse((bool) Order::query()->findOrFail(101)->refunded);
        self::assertFalse((bool) Order::query()->findOrFail(201)->refunded);
    }

    public function test_refund_requires_approval_then_exact_retry_executes_once_and_replays_once(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $page = $this->refundPage([101, 102], 'paid');

        $first = $harness->dispatchRefund($page, 'customer-request', 'refund-K');
        $challenge = $this->assertConfirmationRequired($first);
        self::assertSame(0, $harness->executor->refundExecutions);

        $receipt = $harness->approve($challenge);
        self::assertSame(0, $harness->executor->refundExecutions, 'Approval must not execute refund code.');

        $second = $harness->dispatchRefund($page, 'customer-request', 'refund-K', $receipt);
        self::assertTrue($second->completed);
        self::assertSame(1, $harness->executor->refundExecutions);
        self::assertTrue((bool) Order::query()->findOrFail(101)->refunded);
        self::assertTrue((bool) Order::query()->findOrFail(102)->refunded);

        $replay = $harness->dispatchRefund($page, 'customer-request', 'refund-K');
        self::assertTrue($replay->completed);
        self::assertSame(1, $harness->executor->refundExecutions, 'Completed retry must replay without duplicate refund.');
    }

    public function test_selection_drift_does_not_spend_exact_scope_receipt(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $firstPage = $this->refundPage([101, 102], 'paid');
        $challenge = $this->assertConfirmationRequired(
            $harness->dispatchRefund($firstPage, 'customer-request', 'selection-drift-K'),
        );
        $receipt = $harness->approve($challenge);

        $wrongPage = $this->refundPage([101, 103], 'paid');
        $wrong = $harness->dispatchRefund(
            $wrongPage,
            'customer-request',
            'selection-drift-K',
            $receipt,
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->refundExecutions);

        $exactPage = $this->refundPage([101, 102], 'paid');
        $exact = $harness->dispatchRefund(
            $exactPage,
            'customer-request',
            'selection-drift-K',
            $receipt,
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->refundExecutions);
    }

    public function test_tenant_drift_does_not_spend_exact_scope_receipt(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $firstPage = $this->refundPage([101, 102], 'paid');
        $challenge = $this->assertConfirmationRequired(
            $harness->dispatchRefund($firstPage, 'customer-request', 'tenant-drift-K'),
        );
        $receipt = $harness->approve($challenge);

        $harness->switchTrustedTenant('tenant-b');
        $wrongPage = $this->refundPage([201], 'paid');
        $wrong = $harness->dispatchRefund(
            $wrongPage,
            'customer-request',
            'tenant-drift-K',
            $receipt,
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->refundExecutions);

        $harness->switchTrustedTenant('tenant-a');
        $exactPage = $this->refundPage([101, 102], 'paid');
        $exact = $harness->dispatchRefund(
            $exactPage,
            'customer-request',
            'tenant-drift-K',
            $receipt,
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->refundExecutions);
    }

    public function test_applied_filter_drift_does_not_spend_exact_scope_receipt(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $page = $this->refundPage([101, 102], 'paid');
        $challenge = $this->assertConfirmationRequired(
            $harness->dispatchRefund($page, 'customer-request', 'filter-drift-K'),
        );
        $receipt = $harness->approve($challenge);

        $page->getTableFiltersForm()->fill([
            'status' => ['value' => 'pending'],
        ]);
        self::assertSame(
            'paid',
            $page->getTableFilterState('status')['value'] ?? null,
            'Deferred form edit is not applied authority yet.',
        );

        $page->applyTableFilters();
        $wrong = $harness->dispatchRefund(
            $page,
            'customer-request',
            'filter-drift-K',
            $receipt,
        );
        $this->assertConfirmationRequired($wrong);
        self::assertSame(0, $harness->executor->refundExecutions);

        $this->applyStatusFilter($page, 'paid');
        $exact = $harness->dispatchRefund(
            $page,
            'customer-request',
            'filter-drift-K',
            $receipt,
        );
        self::assertTrue($exact->completed);
        self::assertSame(1, $harness->executor->refundExecutions);
    }

    public function test_same_idempotency_key_cannot_replay_changed_intent_or_authority(): void
    {
        $harness = $this->harness(actorTenant: 'tenant-a', activeTenant: 'tenant-a');
        $page = $this->refundPage([101, 102], 'paid');
        $challenge = $this->assertConfirmationRequired(
            $harness->dispatchRefund($page, 'customer-request', 'replay-K'),
        );
        $receipt = $harness->approve($challenge);
        $completed = $harness->dispatchRefund($page, 'customer-request', 'replay-K', $receipt);
        self::assertTrue($completed->completed);
        self::assertSame(1, $harness->executor->refundExecutions);

        $inputConflict = $harness->dispatchRefund($page, 'different-reason', 'replay-K');
        self::assertFalse($inputConflict->completed);
        self::assertSame('idempotency_conflict', $inputConflict->halt?->code);

        $selectionConflict = $harness->dispatchRefund(
            $this->refundPage([101, 103], 'paid'),
            'customer-request',
            'replay-K',
        );
        self::assertFalse($selectionConflict->completed);
        self::assertSame('idempotency_conflict', $selectionConflict->halt?->code);

        $filterConflict = $harness->dispatchRefund(
            $this->refundPage([101, 102], 'pending'),
            'customer-request',
            'replay-K',
        );
        self::assertFalse($filterConflict->completed);
        self::assertSame('idempotency_conflict', $filterConflict->halt?->code);

        $harness->switchTrustedTenant('tenant-b');
        $tenantPartition = $harness->dispatchRefund(
            $this->refundPage([201], 'paid'),
            'customer-request',
            'replay-K',
        );
        $this->assertConfirmationRequired($tenantPartition);

        self::assertSame(1, $harness->executor->refundExecutions);
    }

    private function harness(string $actorTenant, string $activeTenant): FilamentOrderDemoHarness
    {
        return new FilamentOrderDemoHarness(
            app: $this->app,
            actorTenant: $actorTenant,
            activeTenant: $activeTenant,
        );
    }

    private function editPage(Order $order): EditOrder
    {
        $page = new EditOrder();
        $page->record = $order;

        return $page;
    }

    /** @param list<int> $selectedIds */
    private function refundPage(array $selectedIds, string $status = 'paid'): ListOrders
    {
        $page = $this->listPage();
        $page->selectedTableRecords = $selectedIds;
        $this->applyStatusFilter($page, $status);

        return $page;
    }

    private function listPage(): ListOrders
    {
        $page = new ListOrders();
        $page->bootedInteractsWithTable();

        return $page;
    }

    private function applyStatusFilter(ListOrders $page, string $status): void
    {
        $page->getTableFiltersForm()->fill([
            'status' => ['value' => $status],
        ]);
        $page->applyTableFilters();
    }

    private function assertConfirmationRequired(ActionPipelineOutcome $outcome): ConfirmationChallenge
    {
        self::assertFalse($outcome->completed);
        self::assertSame('confirmation_required', $outcome->halt?->code);
        self::assertInstanceOf(ConfirmationChallenge::class, $outcome->halt?->confirmation);

        return $outcome->halt->confirmation;
    }
}
