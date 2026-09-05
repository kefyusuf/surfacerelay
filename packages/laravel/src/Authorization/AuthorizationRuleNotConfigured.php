<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

/**
 * No Laravel authorization rule is configured for the requested exact
 * ActionDefinition identity (id + version). This is a configuration failure,
 * never interpreted as allow/guest/public: every action passing through the
 * mandatory Authorization stage must have explicit authorization
 * configuration.
 */
final class AuthorizationRuleNotConfigured extends \OutOfBoundsException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'No Laravel authorization rule configured for exact action identity "%s" version %d.',
            $this->id,
            $this->version,
        ));
    }
}
