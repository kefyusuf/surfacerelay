<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

final class InvalidFilamentCurrentSelection extends \RuntimeException
{
    public static function invalidConfiguration(): self
    {
        return new self('Filament current selection configuration is invalid.');
    }

    public static function resolutionFailed(): self
    {
        return new self('Filament current selection resolution failed.');
    }

    public static function limitExceeded(): self
    {
        return new self('Filament current selection exceeds the configured limit.');
    }

    public static function unsupportedValue(): self
    {
        return new self('Filament current selection contains an unsupported value.');
    }

    public static function invalidRecordIdentity(): self
    {
        return new self('Filament current selection record identity is invalid.');
    }

    public static function duplicateIdentity(): self
    {
        return new self('Filament current selection contains a duplicate record identity.');
    }

    public static function ambiguousDuplicateRows(): self
    {
        return new self('Filament current selection uses ambiguous duplicate-row semantics.');
    }
}
