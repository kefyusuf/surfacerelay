<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * Internal pipeline outcome for one dispatch: completed or halted, with the
 * final state and structured halt information. Deliberately NOT the public
 * Action Result taxonomy (succeeded/failed/rejected/confirmation_required) —
 * ActionResultNormalizer (T-110) owns that translation. Carries enough
 * information for the audit finalizer and later normalization.
 */
final readonly class ActionPipelineOutcome
{
    private function __construct(
        public readonly bool $completed,
        public readonly ActionPipelineState $state,
        public readonly ?ActionPipelineStage $haltedAt,
        public readonly ?ActionPipelineHalt $halt,
    ) {}

    public static function completed(ActionPipelineState $state): self
    {
        return new self(true, $state, null, null);
    }

    /**
     * @param ActionPipelineStage|null $haltedAt null when the built-in kernel
     * context-requirement step halted the pipeline before any stage ran.
     */
    public static function halted(ActionPipelineState $state, ?ActionPipelineStage $haltedAt, ActionPipelineHalt $halt): self
    {
        return new self(false, $state, $haltedAt, $halt);
    }
}
