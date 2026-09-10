<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentRecordIdentity extends \RuntimeException
{
    public static function invalid(): self
    {
        return new self('Filament record identity is invalid.');
    }
}
