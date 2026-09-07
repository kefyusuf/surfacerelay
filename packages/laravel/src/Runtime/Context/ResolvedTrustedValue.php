<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * A runtime resolver successfully resolved one non-null trusted value
 * together with provenance. Absence is represented by a resolver returning
 * null — never by this object holding null — so legitimate falsy values
 * (0, false, '', []) remain meaningful resolved states and presence is never
 * decided by PHP truthiness.
 *
 * `confirmationScopeKey` is optional trusted resolver output used only to
 * derive deterministic confirmation scope for domain values that must not be
 * inspected/serialized by the generic runtime. It is not authority by itself.
 */
final readonly class ResolvedTrustedValue
{
    public function __construct(
        public readonly mixed $value,
        public readonly ContextProvenance $provenance,
        public readonly ?string $confirmationScopeKey = null,
    ) {
        if ($this->value === null) {
            throw new \InvalidArgumentException(
                'ResolvedTrustedValue must not hold null; a resolver signals absence by returning null.'
            );
        }
        if ($this->confirmationScopeKey === '') {
            throw new \InvalidArgumentException(
                'ResolvedTrustedValue confirmationScopeKey must be null or a non-empty string.'
            );
        }
    }
}
