<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Context\FilamentActiveFilterContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentActiveFilterContext;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentActiveFilterGatewayIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
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
            'filament_test_records',
            static function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('name');
            },
        );
    }

    public function test_no_exposure_does_not_promote_filter_shaped_input_or_metadata_to_trusted_authority(): void
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();
        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => true],
            'priority' => ['isActive' => false],
        ]);
        $page->applyTableFilters();

        $executor = new CapturingActiveFilterExecutor();
        $outcome = $this->gateway($executor)->dispatch(
            page: $page,
            actionId: 'orders.inspect_filters',
            actionVersion: 1,
            input: ['filters' => ['status' => ['isActive' => false]]],
            surface: 'filament',
            correlationId: 'corr-filter-spoof',
            metadata: [
                'filament/active_filters' => ['status' => ['isActive' => false]],
                'active_filters' => ['status' => ['isActive' => false]],
            ],
        );

        self::assertTrue($outcome->completed);
        self::assertSame(1, $executor->calls);
        self::assertNull($executor->extensionSeen);
    }

    public function test_explicit_typed_exposure_reaches_existing_action_bus_executor(): void
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();
        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => true],
            'priority' => ['isActive' => false],
        ]);
        $page->applyTableFilters();

        $executor = new CapturingActiveFilterExecutor();
        $outcome = $this->gateway($executor)->dispatch(
            page: $page,
            actionId: 'orders.inspect_filters',
            actionVersion: 1,
            input: ['filters' => ['status' => ['isActive' => false]]],
            surface: 'filament',
            correlationId: 'corr-filter-exposed',
            metadata: ['filament/active_filters' => ['spoofed' => true]],
            contextExposure: FilamentContextExposure::activeFilters(),
        );

        self::assertTrue($outcome->completed);
        self::assertSame(1, $executor->calls);
        self::assertNotNull($executor->extensionSeen);
        self::assertSame(
            'filament/active_filters',
            $executor->extensionSeen->key,
        );
        self::assertSame(
            [
                'priority' => $page->getTableFilterState('priority'),
                'status' => $page->getTableFilterState('status'),
            ],
            $executor->extensionSeen->value,
        );
        self::assertSame('filament.active_filters', $executor->extensionSeen->provenance->provider);
    }

    public function test_explicit_exposure_on_non_table_page_fails_before_action_bus_execution(): void
    {
        $executor = new CapturingActiveFilterExecutor();

        try {
            $this->gateway($executor)->dispatch(
                page: new NonRecordPage(),
                actionId: 'orders.inspect_filters',
                actionVersion: 1,
                input: ['filters' => []],
                surface: 'filament',
                correlationId: 'corr-filter-non-table',
                contextExposure: FilamentContextExposure::activeFilters(),
            );
            self::fail('Expected explicit active-filter exposure to fail before execution.');
        } catch (InvalidFilamentActiveFilterContext $e) {
            self::assertSame(
                'Filament active-filter context is unavailable for this page.',
                $e->getMessage(),
            );
            self::assertSame(0, $executor->calls);
            self::assertNull($executor->extensionSeen);
        }
    }

    private function gateway(ActionExecutor $executor): FilamentActionGateway
    {
        return new FilamentActionGateway(
            bus: $this->bus($executor),
            baseComposer: new TrustedContextComposer(
                new NullGatewayFilterActorResolver(),
                new NullGatewayFilterTenantResolver(),
            ),
        );
    }

    private function bus(ActionExecutor $executor): ActionBus
    {
        $registry = new InMemoryActionRegistry();
        $registry->register(new ActionDefinition(
            id: 'orders.inspect_filters',
            version: 1,
            title: 'Inspect active filters',
            description: 'Verify explicitly exposed Filament filter context reaches ActionBus.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['filters' => ['type' => 'object']],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        ));

        return new ActionBus(
            registry: $registry,
            auditor: new NoopActiveFilterGatewayAuditor(),
            handlers: [
                new ActiveFilterGatewayPassthroughStage(ActionPipelineStage::InputValidation),
                new ActiveFilterGatewayPassthroughStage(ActionPipelineStage::Authorization),
                new ActiveFilterGatewayPassthroughStage(ActionPipelineStage::Idempotency),
                new ActiveFilterGatewayPassthroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor),
                new ActiveFilterGatewayPassthroughStage(ActionPipelineStage::OutputPolicy),
            ],
        );
    }
}

final class NullGatewayFilterActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullGatewayFilterTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class CapturingActiveFilterExecutor implements ActionExecutor
{
    public int $calls = 0;

    public ?\SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension $extensionSeen = null;

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        $this->calls++;
        $this->extensionSeen = $context->getTrustedExtension(
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );

        return ['hasActiveFilters' => $this->extensionSeen !== null];
    }
}

final readonly class ActiveFilterGatewayPassthroughStage implements ActionPipelineStageHandler
{
    public function __construct(private ActionPipelineStage $pipelineStage) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::continueWith($state);
    }
}

final class NoopActiveFilterGatewayAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
