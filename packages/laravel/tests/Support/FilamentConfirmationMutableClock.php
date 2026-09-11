<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;

final class FilamentConfirmationMutableClock implements ConfirmationClock
{
    public function __construct(public int $timestamp = 1_800_000_000) {}

    public function now(): int
    {
        return $this->timestamp;
    }

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }
}
