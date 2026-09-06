<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire;

use SurfaceRelay\Laravel\Binding\BindingLifecycle;
use SurfaceRelay\Laravel\Binding\RuntimeBinding;
use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Livewire-specific RuntimeBinding factory.
 *
 * The driver and lifecycle are fixed by construction so callers cannot turn
 * an exact mounted-component descriptor into a persistent/session binding or
 * route it through another driver. Issuance, storage, stale detection, and
 * execution belong to later M2/M3 tasks.
 */
final class LivewireRuntimeBinding
{
    /** @param array<string, mixed> $extensions */
    public static function forComponent(
        string $bindingId,
        ActionDefinition $definition,
        LivewireBindingTarget $target,
        ?string $expiresAt = null,
        array $extensions = [],
    ): RuntimeBinding {
        return new RuntimeBinding(
            bindingId: $bindingId,
            definition: $definition,
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: $target->toArray(),
            expiresAt: $expiresAt,
            extensions: $extensions,
        );
    }

    private function __construct()
    {
    }
}
