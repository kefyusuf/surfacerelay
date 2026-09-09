<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

enum IdempotencyRecordState: string
{
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Indeterminate = 'indeterminate';
}
