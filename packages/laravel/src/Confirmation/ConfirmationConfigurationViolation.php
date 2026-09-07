<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Trusted confirmation authority was injected before server-side verification. */
final class ConfirmationConfigurationViolation extends \LogicException
{
    public static function preMaterializedAuthority(): self
    {
        return new self(
            'Human confirmation authority must be materialized only by the confirmation stage after receipt consumption.'
        );
    }
}
