<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Confirmation\ConfirmationConfigurationViolation;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRequirement;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlanKind;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;

/**
 * Protocol-neutral orchestration shell for one action invocation.
 *
 * Canonical flow per dispatch:
 *
 *     exact registry resolution (kernel)
 *     → trusted context requirement check (kernel, except human_confirmation)
 *     → input_validation → authorization → idempotency → confirmation
 *     → execution → output_policy
 *     → audit finalizer (exactly once, after the final outcome is known)
 *
 * A completed exact idempotency replay is the only internal plan allowed to
 * skip confirmation and execution. Validation and authorization have already
 * rerun for the current invocation; output policy still reruns over the stored
 * pre-policy executor output. Completed replay never manufactures or consumes
 * confirmation authority.
 */
final class ActionBus
{
    /** @var array<string, ActionPipelineStageHandler> keyed by stage value */
    private array $handlers = [];

    /**
     * @param list<ActionPipelineStageHandler> $handlers exactly one handler per
     * ActionPipelineStage; registration order is irrelevant, canonical order
     * is always imposed.
     */
    public function __construct(
        private readonly ActionRegistry $registry,
        private readonly ActionPipelineAuditor $auditor,
        array $handlers,
    ) {
        foreach ($handlers as $index => $handler) {
            if (!$handler instanceof ActionPipelineStageHandler) {
                throw InvalidPipelineConfiguration::invalidHandler($index);
            }
            $stage = $handler->stage()->value;
            if (isset($this->handlers[$stage])) {
                throw InvalidPipelineConfiguration::duplicateStage($handler->stage());
            }
            $this->handlers[$stage] = $handler;
        }
        foreach (ActionPipelineStage::cases() as $stage) {
            if (!isset($this->handlers[$stage->value])) {
                throw InvalidPipelineConfiguration::missingStage($stage);
            }
        }
    }

    public function dispatch(ActionCall $call): ActionPipelineOutcome
    {
        $definition = $this->registry->get($call->actionId, $call->actionVersion);

        $state = new ActionPipelineState(
            definition: $definition,
            input: $call->input,
            context: $call->context,
            bindingId: $call->bindingId,
            confirmationReceipt: $call->confirmationReceipt,
        );

        $missing = [];
        foreach ($definition->contextRequirements as $requirement) {
            if ($requirement === ContextRequirement::HumanConfirmation) {
                continue;
            }
            if (!$call->context->has($requirement)) {
                $missing[] = $requirement->value;
            }
        }
        if ($missing !== []) {
            return $this->finalize($call, ActionPipelineOutcome::halted(
                $state,
                null,
                new ActionPipelineHalt(
                    CoreActionErrorCode::REQUIRED_CONTEXT_MISSING,
                    ['requirements' => $missing],
                ),
            ));
        }

        foreach (ActionPipelineStage::cases() as $stage) {
            if ($this->shouldSkipCompletedReplayStage($stage, $state)) {
                continue;
            }

            $decision = $this->handlers[$stage->value]->process($state);
            if (!$decision->continue) {
                return $this->finalize($call, ActionPipelineOutcome::halted(
                    $decision->state,
                    $stage,
                    $decision->halt ?? new ActionPipelineHalt('halted'),
                ));
            }
            $state = $decision->state;
        }

        if (!$state->hasOutput) {
            throw PipelineInvariantViolation::missingExecutionOutput();
        }

        return $this->finalize($call, ActionPipelineOutcome::completed($state));
    }

    private function shouldSkipCompletedReplayStage(
        ActionPipelineStage $stage,
        ActionPipelineState $state,
    ): bool {
        if ($state->idempotencyPlan?->kind !== IdempotencyExecutionPlanKind::Replay) {
            return false;
        }

        if ($stage === ActionPipelineStage::Confirmation) {
            if (
                ConfirmationRequirement::isRequired($state->definition)
                && $state->context->has(ContextRequirement::HumanConfirmation)
            ) {
                throw ConfirmationConfigurationViolation::preMaterializedAuthority();
            }

            return true;
        }

        return $stage === ActionPipelineStage::Execution;
    }

    private function finalize(ActionCall $call, ActionPipelineOutcome $outcome): ActionPipelineOutcome
    {
        $this->auditor->record($call, $outcome);
        return $outcome;
    }
}
