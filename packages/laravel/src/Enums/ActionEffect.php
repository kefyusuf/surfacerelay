<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

enum ActionEffect: string
{
    case Read = 'read';
    case ReversibleWrite = 'reversible_write';
    case DestructiveWrite = 'destructive_write';
    case ExternalSideEffect = 'external_side_effect';
}
