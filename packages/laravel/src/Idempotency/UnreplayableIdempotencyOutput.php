<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final class UnreplayableIdempotencyOutput extends \RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf('Cannot encode idempotency replay output at "%s".', $path));
    }
}
