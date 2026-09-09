<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * Audit finalizer contract. Called exactly once per dispatch after the final
 * outcome is known — for completed pipelines AND for explicit halts — and
 * never modifies the outcome. Implementations may persist minimized final
 * outcome evidence; persistence policy remains behind this port.
 */
interface ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void;
}
