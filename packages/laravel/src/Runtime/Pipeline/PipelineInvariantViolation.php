<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

/**
 * A completed pipeline finished without an execution output. Because null is
 * a legitimate execution result, execution is tracked by explicit presence;
 * completing without it is an internal pipeline invariant error, not a
 * public action-result failure.
 */
final class PipelineInvariantViolation extends \LogicException
{
    public static function missingExecutionOutput(): self
    {
        return new self(
            'Pipeline completed without an execution output; the execution stage must record '
            . 'its result explicitly (use withOutput, even for null results).'
        );
    }
}
