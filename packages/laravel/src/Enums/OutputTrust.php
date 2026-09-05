<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

enum OutputTrust: string
{
    case TrustedApplicationData = 'trusted_application_data';
    case ContainsUntrustedContent = 'contains_untrusted_content';
    case Sensitive = 'sensitive';
}
