<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

/**
 * A Laravel authorization rule was registered twice for the same exact
 * ActionDefinition identity. No last-wins, first-wins, merge, or overwrite;
 * different versions of the same action id remain independent.
 */
final class DuplicateAuthorizationRule extends \LogicException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'A Laravel authorization rule is already registered for exact action identity "%s" version %d.',
            $this->id,
            $this->version,
        ));
    }
}
