<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Confirmation\FilamentConfirmationBridge;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationPage;

final class FilamentConfirmationGatewayIntegrationTest extends TestCase
{
    public function test_bridge_absent_preserves_confirmation_outcome_without_mounting_modal(): void
    {
        $auditor = new CapturingConfirmationGatewayAuditor();
        $challenge = $this->challenge();
        $page = $this->page();

        $outcome = $this->gateway(
            auditor: $auditor,
            challenge: $challenge,
            withBridge: false,
        )->dispatch(
            page: $page,
            actionId: 'orders.confirm_gateway',
            actionVersion: 1,
            input: [],
            surface: 'filament',
            correlationId: 'corr-no-bridge',
        );

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Confirmation, $outcome->haltedAt);
        self::assertSame($challenge, $outcome->halt?->confirmation);
        self::assertSame($outcome, $auditor->lastOutcome);
        self::assertNull($page->surfaceRelayConfirmationChallengeId);
        self::assertNull($page->getMountedAction());
    }

    public function test_bridge_present_presents_exact_challenge_and_returns_original_outcome(): void
    {
        $auditor = new CapturingConfirmationGatewayAuditor();
        $challenge = $this->challenge();
        $page = $this->page();

        $outcome = $this->gateway(
            auditor: $auditor,
            challenge: $challenge,
            withBridge: true,
        )->dispatch(
            page: $page,
            actionId: 'orders.confirm_gateway',
            actionVersion: 1,
            input: [],
            surface: 'filament',
            correlationId: 'corr-with-bridge',
        );

        self::assertSame($outcome, $auditor->lastOutcome);
        self::assertSame($challenge, $outcome->halt?->confirmation);
        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
        self::assertSame($challenge->summary, $page->surfaceRelayConfirmationSummary);
        self::assertSame($challenge->expiresAt, $page->surfaceRelayConfirmationExpiresAt);
        self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
    }

    public function test_completed_outcome_with_bridge_does_not_present_modal_and_is_returned_unchanged(): void
    {
        $auditor = new CapturingConfirmationGatewayAuditor();
        $page = $this->page();

        $outcome = $this->gateway(
            auditor: $auditor,
            challenge: null,
            withBridge: true,
        )->dispatch(
            page: $page,
            actionId: 'orders.confirm_gateway',
            actionVersion: 1,
            input: [],
            surface: 'filament',
            correlationId: 'corr-completed',
        );

        self::assertTrue($outcome->completed);
        self::assertSame($outcome, $auditor->lastOutcome);
        self::assertNull($page->surfaceRelayConfirmationChallengeId);
        self::assertNull($page->getMountedAction());
    }

    private function gateway(
        CapturingConfirmationGatewayAuditor $auditor,
        ?ConfirmationChallenge $challenge,
        bool $withBridge,
    ): FilamentActionGateway {
        $arguments = [
            'bus' => $this->bus($auditor, $challenge),
            'baseComposer' => new TrustedContextComposer(
                new NullConfirmationGatewayActorResolver(),
                new NullConfirmationGatewayTenantResolver(),
            ),
        ];

        if ($withBridge) {
            $arguments['confirmationBridge'] = new FilamentConfirmationBridge();
        }

        return new FilamentActionGateway(...$arguments);
    }

    private function bus(
        CapturingConfirmationGatewayAuditor $auditor,
        ?ConfirmationChallenge $challenge,
    ): ActionBus {
        $registry = new InMemoryActionRegistry();
        $registry->register(new ActionDefinition(
            id: 'orders.confirm_gateway',
            version: 1,
            title: 'Confirm gateway action',
            description: 'Exercise the optional Filament confirmation bridge hook.',
            inputSchema: ['type' => 'object', 'additionalProperties' => false],
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
            auditor: $auditor,
            handlers: array_map(
                static fn (ActionPipelineStage $stage): ConfirmationGatewayStage => new ConfirmationGatewayStage(
                    $stage,
                    $challenge,
                ),
                ActionPipelineStage::cases(),
            ),
        );
    }

    private function challenge(): ConfirmationChallenge
    {
        return new ConfirmationChallenge(
            str_repeat('G', 43),
            'Approve gateway action',
            '2026-09-11T08:00:00Z',
        );
    }

    private function page(): TestConfirmationPage
    {
        $page = new TestConfirmationPage();
        $page->bootedInteractsWithActions();

        return $page;
    }
}

final readonly class ConfirmationGatewayStage implements ActionPipelineStageHandler
{
    public function __construct(
        private ActionPipelineStage $pipelineStage,
        private ?ConfirmationChallenge $challenge,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if ($this->pipelineStage === ActionPipelineStage::Confirmation && $this->challenge !== null) {
            return ActionPipelineDecision::halt(
                new ActionPipelineHalt(
                    CoreActionErrorCode::CONFIRMATION_REQUIRED,
                    confirmation: $this->challenge,
                ),
                $state,
            );
        }

        if ($this->pipelineStage === ActionPipelineStage::Execution) {
            return ActionPipelineDecision::continueWith($state->withOutput(['ok' => true]));
        }

        return ActionPipelineDecision::continueWith($state);
    }
}

final class CapturingConfirmationGatewayAuditor implements ActionPipelineAuditor
{
    public ?ActionPipelineOutcome $lastOutcome = null;

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->lastOutcome = $outcome;
    }
}

final class NullConfirmationGatewayActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullConfirmationGatewayTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}
