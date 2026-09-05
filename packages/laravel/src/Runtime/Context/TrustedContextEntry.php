<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

/**
 * Runtime-resolved authority/data for one canonical ContextRequirement.
 *
 * Entries are constructed exclusively by trusted runtime resolvers/adapters —
 * never hydrated from caller action input or invocation metadata. Absence of
 * a requirement is represented by the absence of its entry, never by a
 * present entry holding null; this keeps resolved-empty states (e.g. an
 * empty current selection) distinguishable from "not resolved".
 */
final readonly class TrustedContextEntry
{
    public function __construct(
        public readonly ContextRequirement $requirement,
        public readonly mixed $value,
        public readonly ContextProvenance $provenance,
    ) {
        if ($this->value === null) {
            throw new \InvalidArgumentException(sprintf(
                'TrustedContextEntry for "%s" must not hold null; represent absence by omitting the entry.',
                $this->requirement->value,
            ));
        }
    }
}
