<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final class IdempotencyConfigurationViolation extends \RuntimeException
{
    public static function invalidRetention(): self
    {
        return new self('Idempotency retention must be a positive integer.');
    }

    public static function freshPlanRequired(): self
    {
        return new self('Idempotency operation requires a fresh-attempt execution plan.');
    }
}
