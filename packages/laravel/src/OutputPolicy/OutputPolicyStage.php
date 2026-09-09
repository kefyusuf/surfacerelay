<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\OutputPolicy;

use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\PipelineInvariantViolation;

/**
 * Production output-policy stage. Normal output is an exact pass-through;
 * sensitive output is releasable only through an explicit trusted redactor
 * decision and never through an implicit raw-output fallback. Sensitive
 * disclosure failures erase raw output before the pipeline can finalize.
 */
final class OutputPolicyStage implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly ?SensitiveOutputRedactor $sensitiveRedactor = null,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::OutputPolicy;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if (!$state->hasOutput) {
            throw PipelineInvariantViolation::missingExecutionOutput();
        }

        if ($state->definition->outputSensitivity === OutputSensitivity::Normal) {
            return ActionPipelineDecision::continueWith($state);
        }

        if ($this->sensitiveRedactor === null) {
            return $this->failClosed($state);
        }

        try {
            $result = $this->sensitiveRedactor->redact(
                $state->definition,
                $state->output,
                OutputPolicyContext::fromInvocationContext($state->context),
            );
        } catch (\Throwable) {
            return $this->failClosed($state);
        }

        if (!$result->isReleased()) {
            return $this->failClosed($state);
        }

        return ActionPipelineDecision::continueWith($state->withOutput($result->output()));
    }

    private function failClosed(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::halt(
            new ActionPipelineHalt(CoreActionErrorCode::OUTPUT_POLICY_FAILED),
            $state->withoutOutput(),
        );
    }
}
