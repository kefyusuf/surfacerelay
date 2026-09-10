<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentRecordContext extends \RuntimeException
{
    public static function recordResolutionFailed(): self
    {
        return new self('Filament current record resolution failed.');
    }

    public static function invalidRecordState(): self
    {
        return new self('Filament current record state is invalid.');
    }

    public static function invalidRecordIdentity(): self
    {
        return new self('Filament current record identity is invalid.');
    }
}
