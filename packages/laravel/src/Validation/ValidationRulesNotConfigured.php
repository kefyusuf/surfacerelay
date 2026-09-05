<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Validation;

/**
 * No Laravel validation rules are configured for the requested exact
 * ActionDefinition identity (id + version). This is a configuration failure,
 * not an implicit empty rule set: "no rules registered" must never be
 * interpreted as "no constraints" because that would turn a configuration
 * omission into implicit allow. Deliberately registered empty rules are
 * valid configuration and do not trigger this exception.
 */
final class ValidationRulesNotConfigured extends \OutOfBoundsException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'No Laravel validation rules configured for exact action identity "%s" version %d.',
            $this->id,
            $this->version,
        ));
    }
}
