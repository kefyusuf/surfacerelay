<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Stored confirmation state could not be decoded without guessing authority. */
final class CorruptConfirmationRecord extends \RuntimeException
{
    public static function encountered(?\Throwable $previous = null): self
    {
        return new self('Confirmation store contains a corrupt authority record.', previous: $previous);
    }
}
