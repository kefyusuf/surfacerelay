<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Stable machine-readable core error codes currently implemented and tested
 * by the reference runtime. This is NOT a closed enum: ActionError.code is an
 * extensible namespace (D-030) — binding/confirmation/idempotency/adapter
 * layers may add codes only as their behavior is actually implemented.
 */
final class CoreActionErrorCode
{
    public const REQUIRED_CONTEXT_MISSING = 'required_context_missing';

    public const INPUT_VALIDATION_FAILED = 'input_validation_failed';

    public const AUTHORIZATION_DENIED = 'authorization_denied';

    public const CONFIRMATION_REQUIRED = 'confirmation_required';

    private function __construct()
    {
    }
}
