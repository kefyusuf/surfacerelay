<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use RuntimeException;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;

final class FilamentConfirmationMemoryStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];

    public bool $throwOnApprove = false;

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        if (isset($this->records[$tokenHash])) {
            return false;
        }

        $this->records[$tokenHash] = $record;

        return true;
    }

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
    {
        if ($this->throwOnApprove) {
            throw new RuntimeException('confirmation test store unavailable');
        }

        $record = $this->records[$tokenHash] ?? null;
        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Pending
            || $now >= $record->challengeExpiresAt
        ) {
            return false;
        }

        $this->records[$tokenHash] = new ConfirmationRecord(
            state: ConfirmationRecordState::Approved,
            scopeFingerprint: $record->scopeFingerprint,
            summary: $record->summary,
            issuedAt: $record->issuedAt,
            challengeExpiresAt: $record->challengeExpiresAt,
            receiptExpiresAt: $receiptExpiresAt,
        );

        return true;
    }

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Approved
            || $record->scopeFingerprint !== $expectedScopeFingerprint
            || $record->receiptExpiresAt === null
            || $now >= $record->receiptExpiresAt
        ) {
            return false;
        }

        unset($this->records[$tokenHash]);

        return true;
    }

    public function recordForToken(string $token): ?ConfirmationRecord
    {
        return $this->records[hash('sha256', $token)] ?? null;
    }
}
