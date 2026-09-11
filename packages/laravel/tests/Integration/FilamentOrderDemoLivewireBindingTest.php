<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Binding\BindingIdGenerator;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\EditOrder;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\ListOrders;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoPageActions;
use SurfaceRelay\Laravel\Tests\Support\FilamentOrderDemoHarness;

final class FilamentOrderDemoLivewireBindingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', '0123456789abcdef0123456789abcdef');
        $app['config']->set('view.paths', [dirname(__DIR__) . '/Fixtures/views']);
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
        ]);
    }

    public function test_exact_page_methods_produce_existing_livewire_bindings_only(): void
    {
        $runtime = $this->runtime();
        $registry = $this->registry();
        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader($registry),
            new MethodLivewireComponentIdentityResolver(),
            new OrderDemoSequenceBindingIdGenerator(),
        );

        $edit = new EditOrder();
        $edit->setId('order-edit-101');
        $edit->record = Order::query()->findOrFail(101);
        $edit->boot($runtime->pageActions);

        $list = new ListOrders();
        $list->setId('order-list');
        $list->bootedInteractsWithTable();
        $list->boot($runtime->pageActions);

        $recordBinding = $producer->forComponent($edit)[0];
        self::assertSame('livewire', $recordBinding->driver);
        self::assertSame('component', $recordBinding->lifecycle->value);
        self::assertSame('orders.hold_current', $recordBinding->definition->id);
        self::assertSame(1, $recordBinding->definition->version);
        self::assertSame('order-edit-101', $recordBinding->target['componentId']);
        self::assertSame('holdCurrent', $recordBinding->target['method']);
        self::assertSame(['reason'], $recordBinding->target['inputOrder']);
        self::assertSame(1, $recordBinding->target['requiredCount']);
        self::assertArrayNotHasKey('orderId', $recordBinding->target);
        self::assertArrayNotHasKey('tenantId', $recordBinding->target);

        $tableBinding = $producer->forComponent($list)[0];
        self::assertSame('livewire', $tableBinding->driver);
        self::assertSame('component', $tableBinding->lifecycle->value);
        self::assertSame('orders.refund_selected', $tableBinding->definition->id);
        self::assertSame(1, $tableBinding->definition->version);
        self::assertSame('order-list', $tableBinding->target['componentId']);
        self::assertSame('refundSelected', $tableBinding->target['method']);
        self::assertSame(['reason'], $tableBinding->target['inputOrder']);
        self::assertSame(1, $tableBinding->target['requiredCount']);
        self::assertArrayNotHasKey('orderIds', $tableBinding->target);
        self::assertArrayNotHasKey('tenantId', $tableBinding->target);
    }

    public function test_livewire_record_method_executes_through_same_gateway_and_action_bus(): void
    {
        $runtime = $this->runtime();
        $testable = Livewire::test(EditOrder::class, ['record' => 101]);

        $testable->call('holdCurrent', 'manual-review');

        self::assertTrue((bool) Order::query()->findOrFail(101)->held);
        self::assertSame(1, $runtime->harness->executor->holdExecutions);
    }

    public function test_order_page_sources_delegate_without_business_or_pipeline_shortcuts(): void
    {
        foreach ([EditOrder::class, ListOrders::class] as $pageClass) {
            $reflection = new \ReflectionClass($pageClass);
            $source = file_get_contents($reflection->getFileName());
            self::assertIsString($source);

            foreach (['->update(', '->delete(', '::query(', 'ActionBus', 'ActionCall'] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $source, $pageClass . ' must delegate through OrderDemoPageActions.');
            }
        }
    }

    private function runtime(): FilamentOrderDemoLivewireRuntime
    {
        $harness = new FilamentOrderDemoHarness(
            app: $this->app,
            actorTenant: 'tenant-a',
            activeTenant: 'tenant-a',
        );
        $pageActions = new OrderDemoPageActions(
            gateway: $harness->gateway,
            refundIdempotencyKey: 'order-demo-livewire-refund-key',
        );
        $this->app->instance(OrderDemoPageActions::class, $pageActions);

        return new FilamentOrderDemoLivewireRuntime($harness, $pageActions);
    }

    private function registry(): InMemoryActionRegistry
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->holdDefinition());
        $registry->register($this->refundDefinition());

        return $registry;
    }

    private function holdDefinition(): ActionDefinition
    {
        return new ActionDefinition(
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
        );
    }

    private function refundDefinition(): ActionDefinition
    {
        return new ActionDefinition(
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
        );
    }
}

final readonly class FilamentOrderDemoLivewireRuntime
{
    public function __construct(
        public FilamentOrderDemoHarness $harness,
        public OrderDemoPageActions $pageActions,
    ) {}
}

final class OrderDemoSequenceBindingIdGenerator implements BindingIdGenerator
{
    private int $counter = 0;

    public function generate(): string
    {
        $this->counter++;

        return 'order-demo-binding-' . $this->counter;
    }
}
