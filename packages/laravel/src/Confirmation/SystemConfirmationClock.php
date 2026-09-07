<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

final class SystemConfirmationClock implements ConfirmationClock
{
    public function now(): int
    {
        return time();
    }
}
