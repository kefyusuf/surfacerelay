<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\OutputPolicy;

/**
 * Explicit sensitive-output disclosure decision. A released null is a valid
 * release and is therefore distinct from a withheld value.
 */
final readonly class OutputRedactionResult
{
    private function __construct(
        private bool $released,
        private mixed $releasedOutput,
    ) {}

    public static function release(mixed $output): self
    {
        return new self(true, $output);
    }

    public static function withhold(): self
    {
        return new self(false, null);
    }

    public function isReleased(): bool
    {
        return $this->released;
    }

    public function output(): mixed
    {
        if (!$this->released) {
            throw new \LogicException('Withheld output is not releasable.');
        }

        return $this->releasedOutput;
    }
}
