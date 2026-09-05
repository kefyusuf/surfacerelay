<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * Audit finalizer contract. Called exactly once per dispatch after the final
 * outcome is known — for completed pipelines AND for explicit halts — and
 * never modifies the outcome. Persistence belongs to a later task (T-404).
 */
interface ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void;
}
