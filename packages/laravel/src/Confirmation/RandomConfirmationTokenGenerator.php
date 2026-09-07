<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

final class RandomConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
