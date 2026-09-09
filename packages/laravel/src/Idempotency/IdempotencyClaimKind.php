<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

enum IdempotencyClaimKind: string
{
    case Claimed = 'claimed';
    case Replay = 'replay';
    case Conflict = 'conflict';
    case InProgress = 'in_progress';
    case Indeterminate = 'indeterminate';
}
