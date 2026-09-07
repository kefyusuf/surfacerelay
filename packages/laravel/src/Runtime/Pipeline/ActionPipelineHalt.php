<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

/**
 * Structured internal halt information.
 *
 * `details` remains non-authoritative diagnostic structure and must never
 * carry trusted actor/tenant/record/session/receipt values. A real pending
 * ConfirmationChallenge uses its own typed field so result normalization can
 * never fabricate a challenge from arbitrary details.
 */
final readonly class ActionPipelineHalt
{
    public function __construct(
        public readonly string $code,
        public readonly mixed $details = null,
        public readonly ?ConfirmationChallenge $confirmation = null,
    ) {
        if ($this->code === '') {
            throw new \InvalidArgumentException('ActionPipelineHalt code must be a non-empty string.');
        }
    }
}
