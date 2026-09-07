<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/**
 * The runtime cannot derive a deterministic, protocol-neutral confirmation
 * scope from a value. Messages identify only the structural path and never
 * include the rejected value or other authority material.
 */
final class UnrepresentableConfirmationScope extends \RuntimeException
{
    public static function at(string $path): self
    {
        return new self(sprintf(
            'Cannot derive deterministic confirmation scope at "%s".',
            $path,
        ));
    }
}
