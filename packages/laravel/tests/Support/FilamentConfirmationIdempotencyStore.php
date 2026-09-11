<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use RuntimeException;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;

final class FilamentConfirmationIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function find(string $keyHash): ?IdempotencyRecord
    {
        return $this->records[$keyHash] ?? null;
    }

    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult
    {
        $existing = $this->records[$fresh->keyHash] ?? null;
        if ($existing !== null && $existing->isActiveAt($now)) {
            return IdempotencyStoreClaimResult::existing($existing);
        }

        $this->records[$fresh->keyHash] = $fresh;

        return IdempotencyStoreClaimResult::claimed($fresh);
    }

    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void
    {
        $record = $this->records[$keyHash] ?? throw new RuntimeException('Missing T-504 idempotency claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new RuntimeException('T-504 idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $record->keyHash,
            intentFingerprint: $record->intentFingerprint,
            state: IdempotencyRecordState::Completed,
            outputPayload: $outputPayload,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }

    public function markIndeterminate(string $keyHash, string $intentFingerprint): void
    {
        $record = $this->records[$keyHash] ?? throw new RuntimeException('Missing T-504 idempotency claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new RuntimeException('T-504 idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $record->keyHash,
            intentFingerprint: $record->intentFingerprint,
            state: IdempotencyRecordState::Indeterminate,
            outputPayload: null,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }
}
