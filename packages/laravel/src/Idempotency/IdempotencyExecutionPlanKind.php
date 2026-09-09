<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

enum IdempotencyExecutionPlanKind: string
{
    case Bypass = 'bypass';
    case FreshAttempt = 'fresh_attempt';
    case Replay = 'replay';
}
