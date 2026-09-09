<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

/** Storage/runtime failure. Messages are deliberately static and never chain database diagnostics. */
final class IdempotencyStoreUnavailable extends \RuntimeException
{
    public static function operationFailed(): self
    {
        return new self('Idempotency store operation failed.');
    }

    public static function transitionFailed(): self
    {
        return new self('Idempotency store transition failed.');
    }

    public static function claimContention(): self
    {
        return new self('Idempotency store claim could not be established.');
    }
}
