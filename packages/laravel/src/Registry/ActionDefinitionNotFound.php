<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Registry;

/**
 * The requested exact Action Definition identity (id + version) is not
 * registered. Lookups never fall back to another version and never return
 * null; `has()` is the non-throwing existence check.
 */
final class ActionDefinitionNotFound extends \OutOfBoundsException
{
    public function __construct(
        public readonly string $id,
        public readonly int $version,
    ) {
        parent::__construct(sprintf(
            'No Action Definition registered for exact identity "%s" version %d.',
            $id,
            $version,
        ));
    }
}
