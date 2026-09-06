<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Attributes;

use Attribute;

/**
 * Explicit Livewire-specific reference to one exact registered Action Definition.
 *
 * This attribute is an allow-list declaration only. It is not an Action
 * Definition, discovery authorization, invocation authorization, or binding.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ExposeAction
{
    public function __construct(
        public string $id,
        public int $version,
    ) {}
}
