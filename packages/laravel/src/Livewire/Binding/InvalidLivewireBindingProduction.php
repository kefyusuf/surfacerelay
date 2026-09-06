<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Binding;

/** Fail-loud configuration/runtime errors while producing Livewire bindings. */
final class InvalidLivewireBindingProduction extends \RuntimeException
{
    public static function componentIdentityUnavailable(object $component): self
    {
        return new self(sprintf(
            'Livewire component identity is unavailable: %s must provide a callable getId() method.',
            $component::class,
        ));
    }

    public static function componentIdentityInvalid(object $component, mixed $value): self
    {
        return new self(sprintf(
            'Livewire component identity is invalid for %s: getId() must return a non-empty string, got %s.',
            $component::class,
            get_debug_type($value),
        ));
    }

    public static function duplicateGeneratedBindingId(string $bindingId): self
    {
        return new self(sprintf(
            'Binding ID generator produced duplicate ID "%s" within one issuance batch.',
            $bindingId,
        ));
    }
}
