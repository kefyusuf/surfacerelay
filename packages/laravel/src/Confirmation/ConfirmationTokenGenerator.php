<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

interface ConfirmationTokenGenerator
{
    public function generate(): string;
}
