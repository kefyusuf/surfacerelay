<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

/**
 * Trusted server-side Filament context exposure policy.
 *
 * This value is constructed by application/adapter wiring only. It is not
 * hydrated from action input, invocation metadata, requests, or browser data.
 */
final readonly class FilamentContextExposure
{
    private function __construct(private bool $activeFilters) {}

    public static function activeFilters(): self
    {
        return new self(true);
    }

    public function includesActiveFilters(): bool
    {
        return $this->activeFilters;
    }
}
