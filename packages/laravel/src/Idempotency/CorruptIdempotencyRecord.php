<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

/** Corrupt or impossible persisted idempotency state. Messages never embed stored payload or identity data. */
final class CorruptIdempotencyRecord extends \RuntimeException
{
    public static function replayPayload(): self
    {
        return new self('Corrupt idempotency replay payload.');
    }

    public static function invalidHashShape(): self
    {
        return new self('Corrupt idempotency record hash shape.');
    }

    public static function invalidExpiry(): self
    {
        return new self('Corrupt idempotency record expiry.');
    }

    public static function completedWithoutOutput(): self
    {
        return new self('Corrupt completed idempotency record output.');
    }

    public static function unexpectedOutput(): self
    {
        return new self('Corrupt non-completed idempotency record output.');
    }
}
