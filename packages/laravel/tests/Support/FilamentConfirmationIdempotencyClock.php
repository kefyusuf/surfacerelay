<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;

final class FilamentConfirmationIdempotencyClock implements IdempotencyClock
{
    public function __construct(public int $timestamp = 1_800_000_000) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}
