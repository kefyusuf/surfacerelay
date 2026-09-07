<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

final class ConfirmationTokenGenerationFailed extends \RuntimeException
{
    public static function afterCollisions(): self
    {
        return new self('Unable to allocate a unique confirmation token after three attempts.');
    }

    public static function invalidGeneratorOutput(): self
    {
        return new self('Confirmation token generator produced an invalid opaque token.');
    }
}
