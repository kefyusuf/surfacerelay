<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * Internal pipeline architecture for one action invocation. This enum is
 * runtime kernel vocabulary only — it must never leak into the cross-language
 * v0.1 Action Definition contract or any surface protocol. Canonical order
 * equals case declaration order.
 */
enum ActionPipelineStage: string
{
    case InputValidation = 'input_validation';
    case Authorization = 'authorization';
    case Idempotency = 'idempotency';
    case Confirmation = 'confirmation';
    case Execution = 'execution';
    case OutputPolicy = 'output_policy';
}
