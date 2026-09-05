<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * One pipeline stage handler. Later tasks implement this contract with real
 * validation/authorization/confirmation/idempotency/execution/output-policy
 * behavior; the bus imposes canonical order regardless of registration order.
 */
interface ActionPipelineStageHandler
{
    public function stage(): ActionPipelineStage;

    public function process(ActionPipelineState $state): ActionPipelineDecision;
}
