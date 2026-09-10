<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * Two trusted runtime extensions were supplied for the same exact key.
 * Authority ambiguity fails loudly; no first-wins, last-wins, or merge.
 */
final class DuplicateTrustedContextExtension extends \LogicException
{
    public function __construct(
        public readonly string $key,
    ) {
        parent::__construct(sprintf(
            'Duplicate trusted context extension for key "%s".',
            $this->key,
        ));
    }
}
