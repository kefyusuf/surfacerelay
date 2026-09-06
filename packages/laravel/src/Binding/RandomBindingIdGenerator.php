<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Binding;

/** Native-PHP opaque binding ID generator; no external UUID dependency. */
final class RandomBindingIdGenerator implements BindingIdGenerator
{
    public function generate(): string
    {
        return 'sr_' . bin2hex(random_bytes(16));
    }
}
