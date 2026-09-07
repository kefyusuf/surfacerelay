<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

interface IdempotencyClock
{
    public function now(): int;
}
