<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Identity;

use SurfaceRelay\Laravel\Livewire\Binding\InvalidLivewireBindingProduction;

/** Resolves exact component identity from the framework-provided getId() method. */
final class MethodLivewireComponentIdentityResolver implements LivewireComponentIdentityResolver
{
    public function resolve(object $component): string
    {
        if (!method_exists($component, 'getId') || !is_callable([$component, 'getId'])) {
            throw InvalidLivewireBindingProduction::componentIdentityUnavailable($component);
        }

        $componentId = $component->getId();

        if (!is_string($componentId) || $componentId === '') {
            throw InvalidLivewireBindingProduction::componentIdentityInvalid($component, $componentId);
        }

        return $componentId;
    }
}
