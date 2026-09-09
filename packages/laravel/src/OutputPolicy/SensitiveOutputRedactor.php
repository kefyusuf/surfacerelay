<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\OutputPolicy;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Trusted application boundary for deciding what sensitive action output may
 * leave SurfaceRelay. Core owns enforcement; applications own the redaction
 * algorithm and must explicitly release a value.
 */
interface SensitiveOutputRedactor
{
    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult;
}
