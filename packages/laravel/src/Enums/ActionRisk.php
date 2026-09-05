<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

enum ActionRisk: string
{
    case Low = 'low';
    case Moderate = 'moderate';
    case High = 'high';
    case Consequential = 'consequential';
}
