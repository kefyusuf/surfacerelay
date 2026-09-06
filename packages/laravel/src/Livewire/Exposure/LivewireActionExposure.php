<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Exposure;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * One resolved, explicit Livewire action exposure candidate.
 *
 * Component identity, binding identity, lifecycle, authorization, and
 * execution are deliberately absent and belong to later runtime stages.
 */
final readonly class LivewireActionExposure
{
    public function __construct(
        public ActionDefinition $definition,
        public string $method,
    ) {}
}
