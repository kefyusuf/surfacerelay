<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Exposure;

/**
 * Explicit Livewire exposure configuration failure.
 *
 * These errors are developer/application configuration failures, not
 * normalized ActionResult outcomes and not caller-facing authorization
 * denials.
 */
final class InvalidLivewireActionExposure extends \LogicException
{
    public static function actionNotRegistered(string $method, string $id, int $version): self
    {
        return new self(sprintf(
            'Livewire method "%s" exposes unregistered Action Definition "%s" version %d.',
            $method,
            $id,
            $version,
        ));
    }

    public static function methodNotPublic(string $method): self
    {
        return new self(sprintf(
            'Livewire exposure method "%s" must be public.',
            $method,
        ));
    }

    public static function methodStatic(string $method): self
    {
        return new self(sprintf(
            'Livewire exposure method "%s" must be an instance method, not static.',
            $method,
        ));
    }

    public static function duplicateAttribute(string $method): self
    {
        return new self(sprintf(
            'Livewire exposure method "%s" declares ExposeAction more than once.',
            $method,
        ));
    }

    public static function duplicateActionIdentity(
        string $id,
        int $version,
        string $firstMethod,
        string $secondMethod,
    ): self {
        return new self(sprintf(
            'Action Definition "%s" version %d is exposed by multiple methods: "%s" and "%s".',
            $id,
            $version,
            $firstMethod,
            $secondMethod,
        ));
    }
}
