<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * A strict trusted-extension lookup did not find the requested exact key.
 * There is never a fallback to action input, invocation metadata, or another
 * non-authoritative source.
 */
final class TrustedContextExtensionNotAvailable extends \OutOfBoundsException
{
    public function __construct(
        public readonly string $key,
    ) {
        parent::__construct(sprintf(
            'Trusted context extension "%s" is not available.',
            $this->key,
        ));
    }
}
