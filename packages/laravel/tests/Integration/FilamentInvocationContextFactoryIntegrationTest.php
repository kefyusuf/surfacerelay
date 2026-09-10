<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;
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
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecordPage;

final class FilamentActionGatewayIntegrationTest extends TestCase
{
    public function test_exact_filament_page_reaches_existing_action_bus_executor_through_production_gateway(): void
    {
        $record = new TestRecord();
        $record->setRawAttributes(['id' => 701, 'name' => 'exact-active-record']);
        $record->exists = true;

        $page = new TestRecordPage();
        $page->record = $record;

        $executor = new CapturingFilamentRecordExecutor();
        $outcome = $this->gateway($executor)->dispatch(
            page: $page,
            actionId: 'orders.inspect_current',
            actionVersion: 1,
            input: [],
            surface: 'filament',
            correlationId: 'corr-701',
            bindingId: 'livewire-binding-exact-page',
            metadata: ['current_record' => 'attacker-record'],
        );

        self::assertTrue($outcome->completed);
        self::assertSame($record, $executor->recordSeen);
        self::assertSame(['recordKey' => 701], $outcome->state->output);
    }

    public function test_non_record_filament_page_cannot_satisfy_current_record_requirement_through_gateway(): void
    {
        $executor = new CapturingFilamentRecordExecutor();
        $outcome = $this->gateway($executor)->dispatch(
            page: new NonRecordPage(),
            actionId: 'orders.inspect_current',
            actionVersion: 1,
            input: [],
            surface: 'filament',
            correlationId: 'corr-no-record',
            bindingId: 'livewire-binding-index-page',
        );

        self::assertFalse($outcome->completed);
        self::assertSame('required_context_missing', $outcome->halt?->code);
        self::assertNull($executor->recordSeen);
    }

    private function gateway(ActionExecutor $executor): FilamentActionGateway
    {
        return new FilamentActionGateway(
            bus: $this->bus($executor),
            baseComposer: new TrustedContextComposer(
                new NullFilamentActorResolver(),
                new NullFilamentTenantResolver(),
            ),
        );
    }

    private function bus(ActionExecutor $executor): ActionBus
    {
        $registry = new InMemoryActionRegistry();
        $registry->register(new ActionDefinition(
            id: 'orders.inspect_current',
            version: 1,
            title: 'Inspect current order',
            description: 'Verify the current Filament order context reaches the existing ActionBus.',
            inputSchema: [
                'type' => 'object',
                'properties' => [],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::CurrentRecord],
        ));

        return new ActionBus(
            registry: $registry,
            auditor: new NoopFilamentInvocationAuditor(),
            handlers: [
                new FilamentPassthroughStage(ActionPipelineStage::InputValidation),
                new FilamentPassthroughStage(ActionPipelineStage::Authorization),
                new FilamentPassthroughStage(ActionPipelineStage::Idempotency),
                new FilamentPassthroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor),
                new FilamentPassthroughStage(ActionPipelineStage::OutputPolicy),
            ],
        );
    }
}

final class NullFilamentActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullFilamentTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class CapturingFilamentRecordExecutor implements ActionExecutor
{
    public mixed $recordSeen = null;

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        $this->recordSeen = $context->require(ContextRequirement::CurrentRecord)->value;

        return ['recordKey' => $this->recordSeen->getKey()];
    }
}

final readonly class FilamentPassthroughStage implements ActionPipelineStageHandler
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

final class NoopFilamentInvocationAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
