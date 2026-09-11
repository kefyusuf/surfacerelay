<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use RuntimeException;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;

final class FilamentConfirmationSequenceTokenGenerator implements ConfirmationTokenGenerator
{
    /** @param list<string> $tokens */
    public function __construct(private array $tokens) {}

    public function generate(): string
    {
        $token = array_shift($this->tokens);
        if (!is_string($token)) {
            throw new RuntimeException('No confirmation test token available.');
        }

        return $token;
    }
}
