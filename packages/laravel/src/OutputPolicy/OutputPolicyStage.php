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
 * sensitive disclosure is added incrementally by T-403 under fail-closed
 * tests and never falls back to implicit raw release.
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

        throw new \LogicException('Sensitive output policy is not implemented yet.');
    }
}
