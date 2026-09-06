<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Contracts;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Protocol-neutral application execution port for one exact Action Definition.
 *
 * Implementations receive already-validated pipeline input and trusted runtime
 * context separately. Validation, authorization, confirmation, idempotency,
 * output policy, and audit remain responsibilities of their pipeline stages.
 */
interface ActionExecutor
{
    /** @param array<string, mixed> $input */
    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed;
}
