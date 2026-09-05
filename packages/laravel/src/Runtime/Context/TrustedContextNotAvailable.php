<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

/**
 * A strict trusted-context lookup (require()) did not find the requested
 * requirement. There is never a fallback to action input, invocation
 * metadata, or any other non-authoritative source.
 */
final class TrustedContextNotAvailable extends \OutOfBoundsException
{
    public function __construct(
        public readonly ContextRequirement $requirement,
    ) {
        parent::__construct(sprintf(
            'Trusted context requirement "%s" is not available.',
            $this->requirement->value,
        ));
    }
}
