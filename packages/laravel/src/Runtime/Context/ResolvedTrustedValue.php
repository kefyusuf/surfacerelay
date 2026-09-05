<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * A runtime resolver successfully resolved one non-null trusted value
 * together with provenance. Absence is represented by a resolver returning
 * null — never by this object holding null — so legitimate falsy values
 * (0, false, '', []) remain meaningful resolved states and presence is never
 * decided by PHP truthiness.
 */
final readonly class ResolvedTrustedValue
{
    public function __construct(
        public readonly mixed $value,
        public readonly ContextProvenance $provenance,
    ) {
        if ($this->value === null) {
            throw new \InvalidArgumentException(
                'ResolvedTrustedValue must not hold null; a resolver signals absence by returning null.'
            );
        }
    }
}
