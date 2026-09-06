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
 * Halt details are never trusted as-is: for each reserved core code the
 * normalizer reconstructs public details from an expected narrow shape and
 * discards everything else, so a misbehaving stage cannot leak arbitrary
 * data through a recognized halt code. Only the implemented codes below are
 * mapped; unknown halt codes fail loudly (UnmappedPipelineOutcome) instead
 * of being guessed. Configuration/programming exceptions are never converted
 * into caller-visible results.
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
                    $this->sanitizeStringListDetails($halt->details, 'requirements'),
                ),
            ),
            CoreActionErrorCode::INPUT_VALIDATION_FAILED => ActionResult::rejected(
                $correlationId,
                new ActionError(
                    $halt->code,
                    'Input validation failed.',
                    $this->sanitizeStringListDetails($halt->details, 'fields'),
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

    /**
     * Public details for list-of-strings codes may contain only
     * `[$key => list<string>]`; the safe entry is reconstructed from the
     * recognized key and every other key is discarded, so extra (possibly
     * sensitive) keys can never reach the public result. Associative arrays,
     * non-string entries, or empty string entries make the shape untrusted;
     * details are dropped entirely (null).
     *
     * @return array{requirements: list<string>}|array{fields: list<string>}|null
     */
    private function sanitizeStringListDetails(mixed $details, string $key): ?array
    {
        if (!is_array($details) || !isset($details[$key]) || !is_array($details[$key])) {
            return null;
        }

        $entries = $details[$key];
        if (!array_is_list($entries)) {
            return null;
        }

        foreach ($entries as $entry) {
            if (!is_string($entry) || $entry === '') {
                return null;
            }
        }

        return [$key => array_values($entries)];
    }
}
