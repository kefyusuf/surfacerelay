<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Contracts\ActionExecutor;

/**
 * Canonical execution-stage adapter from ActionBus state to application code.
 *
 * The executor receives the exact resolved definition, the pipeline's current
 * input, and the trusted InvocationContext. Application exceptions propagate
 * unchanged; normalization belongs outside this stage.
 */
final readonly class ActionExecutionStage implements ActionPipelineStageHandler
{
    public function __construct(
        private ActionExecutor $executor,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Execution;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $output = $this->executor->execute(
            $state->definition,
            $state->input,
            $state->context,
        );

        return ActionPipelineDecision::continueWith($state->withOutput($output));
    }
}
