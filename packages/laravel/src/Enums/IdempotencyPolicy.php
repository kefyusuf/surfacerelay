<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

enum IdempotencyPolicy: string
{
    case None = 'none';
    case RecommendedKey = 'recommended_key';
    case RequiredKey = 'required_key';
}
