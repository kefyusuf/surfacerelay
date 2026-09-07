<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Store;

/**
 * Laravel cache-backed confirmation authority store.
 *
 * Every read-modify-write/delete mutation occurs while holding one exact
 * per-token distributed lock. The adapter never degrades to an unlocked
 * sequence. Only scalar arrays from ConfirmationRecord::toArray() are cached.
 */
final class CacheConfirmationStore implements ConfirmationStore
{
    private const int LOCK_TTL_SECONDS = 10;
    private const int LOCK_WAIT_SECONDS = 2;

    private readonly Store $store;
    private readonly LockProvider $locks;

    public function __construct(Store $store)
    {
        if (!$store instanceof LockProvider) {
            throw ConfirmationStoreUnavailable::lockProviderRequired();
        }

        $this->store = $store;
        $this->locks = $store;
    }

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        if ($ttlSeconds <= 0) {
            throw new \InvalidArgumentException('Confirmation record TTL must be a positive integer.');
        }

        return $this->withTokenLock($tokenHash, function () use ($tokenHash, $record, $ttlSeconds): bool {
            $key = $this->recordKey($tokenHash);
            if ($this->read($key) !== null) {
                return false;
            }

            $this->write($key, $record->toArray(), $ttlSeconds);
            return true;
        });
    }

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
    {
        if ($receiptExpiresAt <= $now) {
            throw new \InvalidArgumentException('Confirmation receipt expiry must be after the current time.');
        }

        return $this->withTokenLock($tokenHash, function () use ($tokenHash, $now, $receiptExpiresAt): bool {
            $key = $this->recordKey($tokenHash);
            $raw = $this->read($key);
            if ($raw === null) {
                return false;
            }

            $record = $this->decode($raw);
            if ($record->state !== ConfirmationRecordState::Pending) {
                return false;
            }
            if ($now >= $record->challengeExpiresAt) {
                return false;
            }

            $approved = new ConfirmationRecord(
                state: ConfirmationRecordState::Approved,
                scopeFingerprint: $record->scopeFingerprint,
                summary: $record->summary,
                issuedAt: $record->issuedAt,
                challengeExpiresAt: $record->challengeExpiresAt,
                receiptExpiresAt: $receiptExpiresAt,
            );

            $this->write($key, $approved->toArray(), $receiptExpiresAt - $now);
            return true;
        });
    }

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool
    {
        if ($expectedScopeFingerprint === '') {
            throw new \InvalidArgumentException('Expected confirmation scope fingerprint must be non-empty.');
        }

        return $this->withTokenLock($tokenHash, function () use ($tokenHash, $expectedScopeFingerprint, $now): bool {
            $key = $this->recordKey($tokenHash);
            $raw = $this->read($key);
            if ($raw === null) {
                return false;
            }

            $record = $this->decode($raw);
            if ($record->state !== ConfirmationRecordState::Approved) {
                return false;
            }
            if ($record->receiptExpiresAt === null || $now >= $record->receiptExpiresAt) {
                return false;
            }
            if (!hash_equals($record->scopeFingerprint, $expectedScopeFingerprint)) {
                return false;
            }

            $this->delete($key);
            return true;
        });
    }

    private function recordKey(string $tokenHash): string
    {
        return 'surfacerelay:confirmation:record:' . $tokenHash;
    }

    private function lockName(string $tokenHash): string
    {
        return 'surfacerelay:confirmation:lock:' . $tokenHash;
    }

    private function withTokenLock(string $tokenHash, callable $operation): mixed
    {
        try {
            $lock = $this->locks->lock($this->lockName($tokenHash), self::LOCK_TTL_SECONDS);
        } catch (\Throwable $exception) {
            throw ConfirmationStoreUnavailable::lockUnavailable($exception);
        }

        if (!$lock instanceof Lock) {
            throw ConfirmationStoreUnavailable::lockUnavailable();
        }

        try {
            $acquired = $lock->block(self::LOCK_WAIT_SECONDS);
        } catch (\Throwable $exception) {
            throw ConfirmationStoreUnavailable::lockUnavailable($exception);
        }

        if ($acquired !== true) {
            throw ConfirmationStoreUnavailable::lockUnavailable();
        }

        try {
            return $operation();
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // The protected mutation has already reached a definitive
                // outcome. Lock expiry remains bounded by LOCK_TTL_SECONDS;
                // never retry the mutation merely because release failed.
            }
        }
    }

    private function read(string $key): mixed
    {
        try {
            return $this->store->get($key);
        } catch (\Throwable $exception) {
            throw ConfirmationStoreUnavailable::readFailed($exception);
        }
    }

    /** @param array<string, string|int|null> $value */
    private function write(string $key, array $value, int $ttlSeconds): void
    {
        try {
            $stored = $this->store->put($key, $value, $ttlSeconds);
        } catch (\Throwable $exception) {
            throw ConfirmationStoreUnavailable::writeFailed($exception);
        }

        if ($stored !== true) {
            throw ConfirmationStoreUnavailable::writeFailed();
        }
    }

    private function delete(string $key): void
    {
        try {
            $deleted = $this->store->forget($key);
        } catch (\Throwable $exception) {
            throw ConfirmationStoreUnavailable::deleteFailed($exception);
        }

        if ($deleted !== true) {
            throw ConfirmationStoreUnavailable::deleteFailed();
        }
    }

    private function decode(mixed $raw): ConfirmationRecord
    {
        if (!is_array($raw)) {
            throw CorruptConfirmationRecord::encountered();
        }

        try {
            return ConfirmationRecord::fromArray($raw);
        } catch (\Throwable $exception) {
            throw CorruptConfirmationRecord::encountered($exception);
        }
    }
}
