<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClaimKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyConfigurationViolation;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlanKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\UnreplayableIdempotencyOutput;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;

/**
 * Canonical execution-stage adapter from ActionBus state to application code.
 *
 * Bypass invocations preserve the original direct executor behavior. A fresh
 * idempotent attempt must atomically claim execution ownership before
 * application code runs, then persist replayable pre-output-policy output
 * before the pipeline may continue. Claim races never execute twice.
 *
 * Application exceptions propagate unchanged after a best-effort transition
 * to indeterminate. SurfaceRelay never assumes an exception proves that the
 * application/external side effect did not happen.
 */
final readonly class ActionExecutionStage implements ActionPipelineStageHandler
{
    public function __construct(
        private ActionExecutor $executor,
        private ?IdempotencyService $idempotency = null,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Execution;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $plan = $state->idempotencyPlan;
        $freshPlan = $plan?->kind === IdempotencyExecutionPlanKind::FreshAttempt ? $plan : null;

        if ($freshPlan !== null) {
            $claim = $this->idempotencyOrFail()->claim($freshPlan);

            if ($claim->kind === IdempotencyClaimKind::Replay) {
                return ActionPipelineDecision::continueWith($state->withOutput($claim->output));
            }

            $halt = $this->haltForClaimKind($claim->kind, $state);
            if ($halt !== null) {
                return $halt;
            }
        }

        try {
            $output = $this->executor->execute(
                $state->definition,
                $state->input,
                $state->context,
            );
        } catch (\Throwable $exception) {
            if ($freshPlan !== null) {
                $this->bestEffortMarkIndeterminate($freshPlan);
            }
            throw $exception;
        }

        if ($freshPlan !== null) {
            try {
                $this->idempotencyOrFail()->complete($freshPlan, $output);
            } catch (UnreplayableIdempotencyOutput $exception) {
                $this->bestEffortMarkIndeterminate($freshPlan);
                throw $exception;
            }
        }

        return ActionPipelineDecision::continueWith($state->withOutput($output));
    }

    private function idempotencyOrFail(): IdempotencyService
    {
        return $this->idempotency ?? throw IdempotencyConfigurationViolation::executionServiceRequired();
    }

    private function haltForClaimKind(
        IdempotencyClaimKind $kind,
        ActionPipelineState $state,
    ): ?ActionPipelineDecision {
        $code = match ($kind) {
            IdempotencyClaimKind::Claimed => null,
            IdempotencyClaimKind::Conflict => CoreActionErrorCode::IDEMPOTENCY_CONFLICT,
            IdempotencyClaimKind::InProgress => CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS,
            IdempotencyClaimKind::Indeterminate => CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE,
            IdempotencyClaimKind::Replay => null,
        };

        return $code === null
            ? null
            : ActionPipelineDecision::halt(new ActionPipelineHalt($code), $state);
    }

    private function bestEffortMarkIndeterminate(IdempotencyExecutionPlan $plan): void
    {
        try {
            $this->idempotencyOrFail()->markIndeterminate($plan);
        } catch (\Throwable) {
            // The primary executor/codec failure remains authoritative. The
            // durable claim is never deleted or released; an in_progress row
            // conservatively blocks automatic re-execution if this transition
            // could not be persisted.
        }
    }
}
