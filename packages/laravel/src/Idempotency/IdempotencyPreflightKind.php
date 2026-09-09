<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

enum IdempotencyPreflightKind: string
{
    case Fresh = 'fresh';
    case Replay = 'replay';
    case Conflict = 'conflict';
    case InProgress = 'in_progress';
    case Indeterminate = 'indeterminate';
}
