<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

interface AuditEventStore
{
    public function append(AuditEvent $event): void;
}
