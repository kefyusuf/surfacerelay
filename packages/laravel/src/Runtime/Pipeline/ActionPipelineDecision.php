<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * A stage's outcome: continue with (possibly updated) state, or halt the
 * pipeline with structured halt information. Halt codes are INTERNAL
 * pipeline vocabulary — T-110 owns the mapping to public ActionResult
 * semantics, and unknown codes fail loudly rather than being classified.
 */
final readonly class ActionPipelineDecision
{
    private function __construct(
        public readonly bool $continue,
        public readonly ActionPipelineState $state,
        public readonly ?ActionPipelineHalt $halt,
    ) {}

    public static function continueWith(ActionPipelineState $state): self
    {
        return new self(true, $state, null);
    }

    public static function halt(ActionPipelineHalt $halt, ActionPipelineState $state): self
    {
        return new self(false, $state, $halt);
    }
}
