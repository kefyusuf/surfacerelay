<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\OutputPolicy;

use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

/**
 * Production output-policy stage. Normal output is an exact pass-through;
 * sensitive output is releasable only through an explicit trusted redactor
 * decision and never through an implicit raw-output fallback.
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
        if ($state->definition->outputSensitivity === OutputSensitivity::Normal) {
            return ActionPipelineDecision::continueWith($state);
        }

        if ($this->sensitiveRedactor === null) {
            throw new \LogicException('Sensitive output requires a configured redactor.');
        }

        $result = $this->sensitiveRedactor->redact(
            $state->definition,
            $state->output,
            OutputPolicyContext::fromInvocationContext($state->context),
        );

        if (!$result->isReleased()) {
            throw new \LogicException('Sensitive output was withheld.');
        }

        return ActionPipelineDecision::continueWith($state->withOutput($result->output()));
    }
}
