<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Schema\Blueprint;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\ListOrders;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoPageActions;
use SurfaceRelay\Laravel\Tests\Support\FilamentOrderDemoHarness;

final class FilamentOrderDemoReviewHardeningTest extends TestCase
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

        $this->installAuditMigration();

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
        ]);
    }

    public function test_refund_rejects_actor_without_authentication_identifier_before_confirmation(): void
    {
        $harness = $this->harness();
        $harness->actor->set(new GenericUser([
            'id' => null,
            'tenant_id' => 'tenant-a',
            'can_hold' => true,
            'can_refund' => true,
        ]));

        $outcome = $harness->dispatchRefund(
            $this->refundPage([101]),
            'customer-request',
            'review-null-actor-key',
        );

        self::assertFalse($outcome->completed);
        self::assertSame('authorization_denied', $outcome->halt?->code);
        self::assertSame(0, $harness->executor->refundExecutions);
        self::assertFalse((bool) Order::query()->findOrFail(101)->refunded);
    }

    public function test_exposed_refund_adapter_uses_distinct_internal_keys_for_independent_intents(): void
    {
        $harness = $this->harness();
        $actions = new OrderDemoPageActions(
            gateway: $harness->gateway,
            refundIdempotencyKey: 'review-livewire-refund-key',
        );

        $first = $this->refundPage([101]);
        $first->boot($actions);
        self::assertSame(
            ['status' => 'confirmation_required'],
            $first->refundSelected('first-customer-request'),
        );

        $second = $this->refundPage([102]);
        $second->boot($actions);
        self::assertSame(
            ['status' => 'confirmation_required'],
            $second->refundSelected('second-customer-request'),
            'A separate refund intent must not collide with the first intent idempotency key.',
        );

        self::assertSame(0, $harness->executor->refundExecutions);
    }

    private function harness(): FilamentOrderDemoHarness
    {
        return new FilamentOrderDemoHarness(
            app: $this->app,
            actorTenant: 'tenant-a',
            activeTenant: 'tenant-a',
        );
    }

    /** @param list<int> $selectedIds */
    private function refundPage(array $selectedIds): ListOrders
    {
        $page = new ListOrders();
        $page->bootedInteractsWithTable();
        $page->selectedTableRecords = $selectedIds;
        $page->getTableFiltersForm()->fill([
            'status' => ['value' => 'paid'],
        ]);
        $page->applyTableFilters();

        return $page;
    }

    private function installAuditMigration(): void
    {
        $path = dirname(__DIR__, 2)
            . '/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php';
        if (!is_file($path)) {
            throw new \RuntimeException('T-404 audit migration is missing.');
        }

        $migration = require $path;
        $migration->up();
    }
}
