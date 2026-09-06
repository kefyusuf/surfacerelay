<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Binding;

/**
 * Construction-time contract violation for RuntimeBinding values.
 * Deliberately separate from public ActionResult error taxonomy.
 */
final class InvalidRuntimeBinding extends \InvalidArgumentException
{
    public static function bindingId(string $bindingId): self
    {
        return new self(sprintf(
            'Runtime Binding bindingId must be between 1 and 240 characters (%d given).',
            mb_strlen($bindingId, 'UTF-8'),
        ));
    }

    public static function driver(string $driver): self
    {
        return new self(sprintf(
            'Runtime Binding driver "%s" violates the canonical driver grammar ^[a-z][a-z0-9_.:-]{0,79}$.',
            $driver,
        ));
    }

    public static function target(): self
    {
        return new self('Runtime Binding target must be a non-empty string-keyed object.');
    }

    public static function expiresAt(string $expiresAt): self
    {
        return new self(sprintf(
            'Runtime Binding expiresAt must be a valid RFC3339 date-time string (got: %s).',
            var_export($expiresAt, true),
        ));
    }

    public static function extensionKey(mixed $key): self
    {
        return new self(sprintf(
            'Runtime Binding extension key must match namespace/key grammar (got: %s).',
            var_export($key, true),
        ));
    }
}
