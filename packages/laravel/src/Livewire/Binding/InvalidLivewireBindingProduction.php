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

    public static function methodSignature(string $method, string $reason): self
    {
        return new self(sprintf(
            'Livewire exposed method "%s" has an unsupported browser-call signature: %s.',
            $method,
            $reason,
        ));
    }

    public static function inputSchema(string $method, string $reason): self
    {
        return new self(sprintf(
            'Livewire exposed method "%s" cannot be mapped from Action input properties: %s.',
            $method,
            $reason,
        ));
    }

    public static function reservedMethod(string $method): self
    {
        return new self(sprintf(
            'Livewire exposed method "%s" is reserved by the public $wire browser surface.',
            $method,
        ));
    }

    public static function publicPropertyCollision(string $method): self
    {
        return new self(sprintf(
            'Livewire exposed method "%s" collides with a public component property and cannot be resolved safely through $wire.',
            $method,
        ));
    }

    public static function outputIncompatible(string $method, string $returnType): self
    {
        return new self(sprintf(
            'Livewire exposed method "%s" declares output-incompatible return type "%s" for an Action with outputSchema.',
            $method,
            $returnType,
        ));
    }
}
