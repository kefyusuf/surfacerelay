<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use DateTimeImmutable;
use DateTimeZone;

final readonly class SystemAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
