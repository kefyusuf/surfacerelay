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
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
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
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentCurrentSelectionGatewayIntegrationTest extends TestCase
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

    public function test_exact_filament_selection_reaches_existing_action_bus_through_production_gateway(): void
    {
        TestRecord::query()->create(['id' => 22, 'name' => 'B']);
        TestRecord::query()->create(['id' => 11, 'name' => 'A']);

        $page = new TestTablePage();
        $page->bootedInteractsWithTable();
        $page->selectedTableRecords = [22, 11];

        $executor = new CapturingFilamentSelectionExecutor();
        $outcome = $this->gateway($executor)->dispatch(
            page: $page,
            actionId: 'orders.inspect_selection',
            actionVersion: 1,
            input: ['selectedTableRecords' => [999]],
            surface: 'filament',
            correlationId: 'corr-selection',
            bindingId: 'livewire-binding-table-page',
            metadata: ['current_selection' => [999]],
        );

        self::assertTrue($outcome->completed);
        self::assertSame([11, 22], $executor->selectedIdsSeen);
        self::assertSame(['selectedIds' => [11, 22]], $outcome->state->output);
    }

    private function gateway(ActionExecutor $executor): FilamentActionGateway
    {
        return new FilamentActionGateway(
            bus: $this->bus($executor),
            baseComposer: new TrustedContextComposer(
                new NullSelectionActorResolver(),
                new NullSelectionTenantResolver(),
            ),
        );
    }

    private function bus(ActionExecutor $executor): ActionBus
    {
        $registry = new InMemoryActionRegistry();
        $registry->register(new ActionDefinition(
            id: 'orders.inspect_selection',
            version: 1,
            title: 'Inspect current selection',
            description: 'Verify trusted Filament selection reaches the existing ActionBus.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['selectedTableRecords' => ['type' => 'array']],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::CurrentSelection],
        ));

        return new ActionBus(
            registry: $registry,
            auditor: new NoopFilamentSelectionAuditor(),
            handlers: [
                new FilamentSelectionPassthroughStage(ActionPipelineStage::InputValidation),
                new FilamentSelectionPassthroughStage(ActionPipelineStage::Authorization),
                new FilamentSelectionPassthroughStage(ActionPipelineStage::Idempotency),
                new FilamentSelectionPassthroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor),
                new FilamentSelectionPassthroughStage(ActionPipelineStage::OutputPolicy),
            ],
        );
    }
}

final class NullSelectionActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullSelectionTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class CapturingFilamentSelectionExecutor implements ActionExecutor
{
    /** @var list<int>|null */
    public ?array $selectedIdsSeen = null;

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        $selection = $context->require(ContextRequirement::CurrentSelection)->value;
        $this->selectedIdsSeen = array_map(
            static fn (TestRecord $record): int => (int) $record->getKey(),
            $selection,
        );

        return ['selectedIds' => $this->selectedIdsSeen];
    }
}

final readonly class FilamentSelectionPassthroughStage implements ActionPipelineStageHandler
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

final class NoopFilamentSelectionAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
