<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * The normalizer encountered an internal pipeline halt code it has no
 * mapping for. Halts are never silently classified as rejected/failed —
 * future stages (e.g. confirmation) have distinct public semantics, and
 * guessing would misclassify agent-visible results.
 */
final class UnmappedPipelineOutcome extends \LogicException
{
    public static function forHaltCode(string $code): self
    {
        return new self(sprintf(
            'No public ActionResult mapping exists for internal pipeline halt code "%s".',
            $code,
        ));
    }
}
