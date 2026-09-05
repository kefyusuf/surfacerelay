<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Registry;

/**
 * Raised when the exact identity (id + version) is registered twice.
 * Identity determines uniqueness, not metadata equality; even a definition
 * with identical fields must be rejected. This is a configuration bug and is
 * deliberately separate from the invocation result error taxonomy (T-110).
 */
final class DuplicateActionDefinition extends \LogicException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'Action Definition "%s" version %d is already registered.',
            $id,
            $version,
        ));
    }
}
