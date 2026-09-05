<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Contracts;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Authorization port for the mandatory Authorization pipeline stage.
 * Protocol-neutral: implementations decide HOW (e.g. a framework Gate), but
 * this contract never names one. Answers "may this trusted actor invoke this
 * exact action, in this context?" for already-validated input; denial is a
 * boolean decision, never an exception control flow.
 */
interface ActionAuthorizer
{
    /**
     * @param array<string, mixed> $input validated pipeline input (T-107 output)
     */
    public function allows(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): bool;
}
