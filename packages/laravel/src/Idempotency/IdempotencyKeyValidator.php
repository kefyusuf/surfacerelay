<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

/** Runtime lexical validation for caller-supplied idempotency keys. */
final class IdempotencyKeyValidator
{
    public function isValid(string $key): bool
    {
        $length = mb_strlen($key, 'UTF-8');

        return $length >= 1 && $length <= 240;
    }
}
