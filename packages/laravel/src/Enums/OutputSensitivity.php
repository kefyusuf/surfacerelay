<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

/**
 * Confidentiality classification of an action's output. Independent of
 * OutputContentTrust (D-032): sensitivity drives output policy/redaction and
 * never suppresses (nor is suppressed by) the untrusted-content signal.
 */
enum OutputSensitivity: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';
}
