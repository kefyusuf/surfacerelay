<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

/**
 * Two trusted entries were supplied for the same ContextRequirement.
 * Authority ambiguity must stay visible: no first-wins, last-wins, or merge.
 */
final class DuplicateTrustedContext extends \LogicException
{
    public function __construct(
        public readonly ContextRequirement $requirement,
    ) {
        parent::__construct(sprintf(
            'Duplicate trusted context entry for requirement "%s".',
            $this->requirement->value,
        ));
    }
}
