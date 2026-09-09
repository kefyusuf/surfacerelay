<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

enum AuditOutcomeKind: string
{
    case Completed = 'completed';
    case Halted = 'halted';
}
