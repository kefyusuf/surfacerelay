<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

final class AuditConfigurationViolation extends \RuntimeException
{
    public static function clockMustReturnUtc(): self
    {
        return new self('Audit clock must return a UTC DateTimeImmutable.');
    }
}
