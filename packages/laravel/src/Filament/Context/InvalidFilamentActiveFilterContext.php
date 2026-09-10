<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentActiveFilterContext extends \RuntimeException
{
    public static function unavailable(): self
    {
        return new self('Filament active-filter context is unavailable for this page.');
    }

    public static function resolutionFailed(): self
    {
        return new self('Filament active-filter context resolution failed.');
    }

    public static function unrepresentableState(): self
    {
        return new self('Filament active-filter state is not deterministically representable.');
    }
}
