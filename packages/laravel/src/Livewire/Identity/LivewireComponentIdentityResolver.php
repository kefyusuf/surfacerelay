<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Identity;

/** Resolves the exact trusted identity of the mounted Livewire component object. */
interface LivewireComponentIdentityResolver
{
    public function resolve(object $component): string;
}
