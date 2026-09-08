<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

/** Canonical confirmation gate after idempotency preflight and before execution. */
final readonly class ConfirmationStage implements ActionPipelineStageHandler
{
    public function __construct(
        private ConfirmationService $service,
        private ConfirmationScopeHasher $scopeHasher,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Confirmation;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if (!$this->requiresConfirmation($state->definition)) {
            return ActionPipelineDecision::continueWith($state);
        }

        if ($state->context->has(ContextRequirement::HumanConfirmation)) {
            throw ConfirmationConfigurationViolation::preMaterializedAuthority();
        }

        $scope = $this->scopeHasher->fingerprint($state);
        $candidate = $state->confirmationReceipt;

        if ($candidate !== null && $this->service->consumeReceipt($candidate, $scope)) {
            $entry = new TrustedContextEntry(
                ContextRequirement::HumanConfirmation,
                new VerifiedConfirmation(),
                new ContextProvenance('surfacerelay.confirmation'),
                confirmationScopeKey: 'verified',
            );

            return ActionPipelineDecision::continueWith(
                $state->withContext($state->context->withTrustedEntry($entry)),
            );
        }

        $challenge = $this->service->issueChallenge($scope, $state->definition->title);

        return ActionPipelineDecision::halt(
            new ActionPipelineHalt(
                CoreActionErrorCode::CONFIRMATION_REQUIRED,
                confirmation: $challenge,
            ),
            $state,
        );
    }

    private function requiresConfirmation(ActionDefinition $definition): bool
    {
        return $definition->risk === ActionRisk::Consequential
            || in_array(ContextRequirement::HumanConfirmation, $definition->contextRequirements, true);
    }
}
