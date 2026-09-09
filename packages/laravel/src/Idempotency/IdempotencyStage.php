<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

/** Server-side idempotency policy validation and preflight only; execution ownership is claimed later. */
final readonly class IdempotencyStage implements ActionPipelineStageHandler
{
    public function __construct(
        private IdempotencyKeyValidator $validator,
        private IdempotencyKeyHasher $keyHasher,
        private IdempotencyIntentHasher $intentHasher,
        private IdempotencyService $service,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Idempotency;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $policy = $state->definition->idempotency;
        $key = $state->context->idempotencyKey;

        if ($policy === IdempotencyPolicy::None) {
            return ActionPipelineDecision::continueWith(
                $state->withIdempotencyPlan(IdempotencyExecutionPlan::bypass()),
            );
        }

        if ($key === null) {
            if ($policy === IdempotencyPolicy::RecommendedKey) {
                return ActionPipelineDecision::continueWith(
                    $state->withIdempotencyPlan(IdempotencyExecutionPlan::bypass()),
                );
            }

            return $this->halt(CoreActionErrorCode::IDEMPOTENCY_KEY_REQUIRED, $state);
        }

        if (!$this->validator->isValid($key)) {
            return $this->halt(CoreActionErrorCode::IDEMPOTENCY_KEY_INVALID, $state);
        }

        $keyHash = $this->keyHasher->hash($state, $key);
        $intentFingerprint = $this->intentHasher->fingerprint($state);
        $preflight = $this->service->preflight($keyHash, $intentFingerprint);

        return match ($preflight->kind) {
            IdempotencyPreflightKind::Fresh => ActionPipelineDecision::continueWith(
                $state->withIdempotencyPlan(
                    IdempotencyExecutionPlan::fresh($keyHash, $intentFingerprint),
                ),
            ),
            IdempotencyPreflightKind::Replay => ActionPipelineDecision::continueWith(
                $state
                    ->withIdempotencyPlan(
                        IdempotencyExecutionPlan::replay(
                            $keyHash,
                            $intentFingerprint,
                            $preflight->output,
                        ),
                    )
                    ->withOutput($preflight->output),
            ),
            IdempotencyPreflightKind::Conflict => $this->halt(
                CoreActionErrorCode::IDEMPOTENCY_CONFLICT,
                $state,
            ),
            IdempotencyPreflightKind::InProgress => $this->halt(
                CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS,
                $state,
            ),
            IdempotencyPreflightKind::Indeterminate => $this->halt(
                CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE,
                $state,
            ),
        };
    }

    private function halt(string $code, ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::halt(new ActionPipelineHalt($code), $state);
    }
}
