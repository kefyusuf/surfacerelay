<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

interface ConfirmationClock
{
    public function now(): int;
}
