<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * Structured halt information: a stable machine-readable code plus optional
 * non-authoritative details. This describes why execution stopped; it never
 * carries trusted authority (no actor/tenant/record/session/receipt values —
 * only identifiers such as requirement or field names). T-110 maps codes to
 * public ActionResult semantics; unknown codes fail loudly instead of being
 * silently classified.
 */
final readonly class ActionPipelineHalt
{
    public function __construct(
        public readonly string $code,
        public readonly mixed $details = null,
    ) {
        if ($this->code === '') {
            throw new \InvalidArgumentException('ActionPipelineHalt code must be a non-empty string.');
        }
    }
}
