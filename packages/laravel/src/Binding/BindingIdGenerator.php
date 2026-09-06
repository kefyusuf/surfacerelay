<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Binding;

/**
 * Generates fresh opaque identifiers for newly issued Runtime Binding instances.
 *
 * Implementations must not derive IDs from action/component/method identity.
 */
interface BindingIdGenerator
{
    public function generate(): string;
}
