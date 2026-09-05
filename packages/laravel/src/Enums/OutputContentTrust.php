<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

/**
 * Whether downstream agent consumers must treat an action's output as
 * untrusted content. Independent of OutputSensitivity (D-032): sensitive
 * output may also contain untrusted content, and the untrusted-content
 * signal must never be suppressed by the sensitivity classification.
 */
enum OutputContentTrust: string
{
    case TrustedApplicationData = 'trusted_application_data';
    case ContainsUntrustedContent = 'contains_untrusted_content';
}
