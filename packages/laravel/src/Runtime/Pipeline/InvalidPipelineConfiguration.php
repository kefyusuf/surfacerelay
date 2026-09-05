<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * The pipeline was constructed without a required security/normal stage, with
 * a duplicate handler for one stage, or with another impossible arrangement.
 * Security stages must never silently become pass-through defaults.
 */
final class InvalidPipelineConfiguration extends \LogicException
{
    public static function missingStage(ActionPipelineStage $stage): self
    {
        return new self(sprintf(
            'Pipeline configuration is missing the required "%s" stage handler.',
            $stage->value,
        ));
    }

    public static function duplicateStage(ActionPipelineStage $stage): self
    {
        return new self(sprintf(
            'Pipeline configuration provides more than one handler for the "%s" stage.',
            $stage->value,
        ));
    }

    public static function invalidHandler(int $index): self
    {
        return new self(sprintf(
            'Pipeline handler at index %d does not implement %s.',
            $index,
            ActionPipelineStageHandler::class,
        ));
    }
}
