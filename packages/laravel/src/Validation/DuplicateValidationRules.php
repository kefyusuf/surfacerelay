<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Validation;

/**
 * Laravel validation rules were registered twice for the same exact
 * ActionDefinition identity. No last-wins, first-wins, merge, or overwrite
 * semantics; identity determines uniqueness. Different versions of the same
 * action id remain independent rule sets.
 */
final class DuplicateValidationRules extends \LogicException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'Laravel validation rules are already registered for exact action identity "%s" version %d.',
            $this->id,
            $this->version,
        ));
    }
}
