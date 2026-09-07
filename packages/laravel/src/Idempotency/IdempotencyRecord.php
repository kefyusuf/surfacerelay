<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyRecord
{
    public function __construct(
        public string $keyHash,
        public string $intentFingerprint,
        public IdempotencyRecordState $state,
        public ?string $outputPayload,
        public int $createdAt,
        public int $expiresAt,
    ) {
        if (preg_match('/^[a-f0-9]{64}$/D', $this->keyHash) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $this->intentFingerprint) !== 1) {
            throw CorruptIdempotencyRecord::invalidHashShape();
        }
        if ($this->expiresAt <= $this->createdAt) {
            throw CorruptIdempotencyRecord::invalidExpiry();
        }
        if ($this->state === IdempotencyRecordState::Completed && $this->outputPayload === null) {
            throw CorruptIdempotencyRecord::completedWithoutOutput();
        }
        if ($this->state !== IdempotencyRecordState::Completed && $this->outputPayload !== null) {
            throw CorruptIdempotencyRecord::unexpectedOutput();
        }
    }

    public function isActiveAt(int $now): bool
    {
        return $now < $this->expiresAt;
    }
}
