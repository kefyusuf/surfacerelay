<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Fail-closed operational/configuration failure of the confirmation authority store. */
final class ConfirmationStoreUnavailable extends \RuntimeException
{
    public static function lockProviderRequired(): self
    {
        return new self('Confirmation store must support distributed lock semantics.');
    }

    public static function lockUnavailable(?\Throwable $previous = null): self
    {
        return new self('Confirmation store lock could not be acquired.', previous: $previous);
    }

    public static function readFailed(?\Throwable $previous = null): self
    {
        return new self('Confirmation store read failed.', previous: $previous);
    }

    public static function writeFailed(?\Throwable $previous = null): self
    {
        return new self('Confirmation store write failed.', previous: $previous);
    }

    public static function deleteFailed(?\Throwable $previous = null): self
    {
        return new self('Confirmation store delete failed.', previous: $previous);
    }
}
