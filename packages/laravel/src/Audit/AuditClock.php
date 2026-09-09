<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use DateTimeImmutable;

interface AuditClock
{
    public function now(): DateTimeImmutable;
}
