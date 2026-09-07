<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

/** Deterministic idempotency scope failure without rejected-value leakage. */
final class UnrepresentableIdempotencyScope extends \RuntimeException
{
    public readonly string $path;

    private function __construct(string $path)
    {
        $this->path = $path;
        parent::__construct(sprintf(
            'Cannot derive deterministic idempotency scope at "%s".',
            $path,
        ));
    }

    public static function at(string $path): self
    {
        return new self($path);
    }
}
