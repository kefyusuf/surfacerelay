<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Stable machine-readable core error codes currently implemented and tested
 * by the reference runtime. This is NOT a closed enum: ActionError.code is an
 * extensible namespace (D-030) — binding/confirmation/idempotency/output-policy/
 * adapter layers may add codes only as their behavior is actually implemented.
 */
final class CoreActionErrorCode
{
    public const REQUIRED_CONTEXT_MISSING = 'required_context_missing';

    public const INPUT_VALIDATION_FAILED = 'input_validation_failed';

    public const AUTHORIZATION_DENIED = 'authorization_denied';

    public const CONFIRMATION_REQUIRED = 'confirmation_required';

    public const IDEMPOTENCY_KEY_REQUIRED = 'idempotency_key_required';

    public const IDEMPOTENCY_KEY_INVALID = 'idempotency_key_invalid';

    public const IDEMPOTENCY_CONFLICT = 'idempotency_conflict';

    public const IDEMPOTENCY_IN_PROGRESS = 'idempotency_in_progress';

    public const IDEMPOTENCY_INDETERMINATE = 'idempotency_indeterminate';

    public const OUTPUT_POLICY_FAILED = 'output_policy_failed';

    private function __construct()
    {
    }
}
