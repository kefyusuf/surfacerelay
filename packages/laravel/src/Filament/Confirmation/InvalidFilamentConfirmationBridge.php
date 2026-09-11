<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Confirmation;

final class InvalidFilamentConfirmationBridge extends \RuntimeException
{
    public static function invalidOutcome(): self
    {
        return new self('Filament confirmation outcome is invalid.');
    }

    public static function hostUnavailable(): self
    {
        return new self('Filament confirmation bridge is unavailable for this page.');
    }

    public static function presentationConflict(): self
    {
        return new self('Filament confirmation presentation conflicts with active page state.');
    }

    public static function presentationFailed(): self
    {
        return new self('Filament confirmation presentation failed.');
    }

    public static function serviceUnavailable(): self
    {
        return new self('Filament confirmation service is unavailable.');
    }

    public static function approvalFailed(): self
    {
        return new self('Filament confirmation approval failed.');
    }
}
