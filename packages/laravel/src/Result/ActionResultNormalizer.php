<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;

/**
 * Protocol-neutral mapper from internal pipeline outcomes to public
 * ActionResult values. Pure translation: correlationId comes from the
 * outcome's InvocationContext, output is carried through untouched (no
 * redaction — the output-policy stage owns transformations), meta starts
 * empty, and no trusted context (actor, tenant, records, selection, session,
 * receipts, provenance, tokens) is ever auto-projected into results.
 *
 * Current mappings (only implemented behavior):
 *     completed + output                       → succeeded (data may be null)
 *     required_context_missing                 → rejected
 *     input_validation_failed                  → rejected
 *     authorization_denied                     → rejected
 *
 * Unknown halt codes fail loudly (UnmappedPipelineOutcome) instead of being
 * guessed: confirmation semantics belong to T-401, binding codes to M2
 * (D-026 stays PROPOSED until implemented). Configuration/programming
 * exceptions are never converted into caller-visible results.
 */
final class ActionResultNormalizer
{
    public function normalize(ActionPipelineOutcome $outcome): ActionResult
    {
        $correlationId = $outcome->state->context->correlationId;

        if ($outcome->completed) {
            return ActionResult::succeeded($correlationId, $outcome->state->output);
        }

        $halt = $outcome->halt;
        if ($halt === null) {
            throw UnmappedPipelineOutcome::forHaltCode('<missing halt>');
        }

        return match ($halt->code) {
            CoreActionErrorCode::REQUIRED_CONTEXT_MISSING => ActionResult::rejected(
                $correlationId,
                new ActionError(
                    $halt->code,
                    'Required trusted context is unavailable.',
                    $halt->details,
                ),
            ),
            CoreActionErrorCode::INPUT_VALIDATION_FAILED => ActionResult::rejected(
                $correlationId,
                new ActionError(
                    $halt->code,
                    'Input validation failed.',
                    $halt->details,
                ),
            ),
            CoreActionErrorCode::AUTHORIZATION_DENIED => ActionResult::rejected(
                $correlationId,
                new ActionError(
                    $halt->code,
                    'Authorization denied.',
                ),
            ),
            default => throw UnmappedPipelineOutcome::forHaltCode($halt->code),
        };
    }
}
