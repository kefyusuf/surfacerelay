<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

final readonly class AuditTrustedContextEntry
{
    public function __construct(
        public ContextRequirement $requirement,
        public string $provider,
    ) {
        if ($this->provider === '') {
            throw new \InvalidArgumentException('Audit trusted-context provider must be non-empty.');
        }
    }
}
