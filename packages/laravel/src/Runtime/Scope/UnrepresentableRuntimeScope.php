<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Scope;

/**
 * The runtime cannot derive deterministic scope identity from a value.
 * Messages expose only the structural path, never the rejected value.
 */
final class UnrepresentableRuntimeScope extends \RuntimeException
{
    public readonly string $path;

    private function __construct(string $path)
    {
        $this->path = $path;
        parent::__construct(sprintf(
            'Cannot derive deterministic runtime scope at "%s".',
            $path,
        ));
    }

    public static function at(string $path): self
    {
        return new self($path);
    }
}
