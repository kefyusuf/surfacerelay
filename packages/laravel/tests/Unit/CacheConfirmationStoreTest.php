<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Store;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\CacheConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStoreUnavailable;
use SurfaceRelay\Laravel\Confirmation\CorruptConfirmationRecord;

final class CacheConfirmationStoreTest extends TestCase
{
    private const string HASH = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function test_cache_store_types_exist(): void
    {
        self::assertTrue(class_exists(CacheConfirmationStore::class), 'CacheConfirmationStore must exist.');
        self::assertTrue(class_exists(ConfirmationStoreUnavailable::class), 'ConfirmationStoreUnavailable must exist.');
        self::assertTrue(class_exists(CorruptConfirmationRecord::class), 'CorruptConfirmationRecord must exist.');
    }

    public function test_constructor_rejects_store_without_lock_provider(): void
    {
        $this->requireTypes();
        $this->expectException(ConfirmationStoreUnavailable::class);
        new CacheConfirmationStore(new NonLockingCacheStore());
    }

    public function test_create_pending_is_locked_refuses_collision_and_writes_scalar_array_with_requested_ttl(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $adapter = new CacheConfirmationStore($store);
        $record = $this->pendingRecord();

        self::assertTrue($adapter->createPending(self::HASH, $record, 300));
        self::assertSame('surfacerelay:confirmation:record:' . self::HASH, $store->lastPutKey);
        self::assertSame($record->toArray(), $store->lastPutValue);
        self::assertSame(300, $store->lastPutSeconds);
        $this->assertMutationWasInsideExactTokenLock($store, 'put');

        $store->resetLog();
        self::assertFalse($adapter->createPending(self::HASH, $record, 300));
        self::assertSame(0, $store->putCalls, 'Collision must not overwrite the existing pending record.');
        $this->assertMutationWasInsideExactTokenLock($store, null);
    }

    public function test_approve_pending_is_locked_and_refuses_missing_non_pending_expired_or_corrupt_records(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $adapter = new CacheConfirmationStore($store);

        self::assertFalse($adapter->approvePending(self::HASH, 120, 240));
        $this->assertMutationWasInsideExactTokenLock($store, null);

        $store->seedRaw(self::HASH, ['corrupt' => true]);
        $store->resetLog();
        try {
            $adapter->approvePending(self::HASH, 120, 240);
            self::fail('Expected corrupt record to fail closed.');
        } catch (CorruptConfirmationRecord $exception) {
            self::assertStringNotContainsString(self::HASH, $exception->getMessage());
        }
        self::assertSame(0, $store->putCalls);
        self::assertSame(1, $store->lastLock?->releaseCalls);

        $approved = new ConfirmationRecord(
            state: ConfirmationRecordState::Approved,
            scopeFingerprint: str_repeat('b', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 400,
            receiptExpiresAt: 220,
        );
        $store->seedRaw(self::HASH, $approved->toArray());
        $store->resetLog();
        self::assertFalse($adapter->approvePending(self::HASH, 130, 260));
        self::assertSame(0, $store->putCalls, 'Repeat approval must not rewrite or extend an approved record.');
        self::assertSame(220, $store->rawRecord(self::HASH)['receiptExpiresAt']);

        $expired = new ConfirmationRecord(
            state: ConfirmationRecordState::Pending,
            scopeFingerprint: str_repeat('c', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 120,
        );
        $store->seedRaw(self::HASH, $expired->toArray());
        $store->resetLog();
        self::assertFalse($adapter->approvePending(self::HASH, 120, 240), 'Expiry equality is invalid.');
        self::assertSame(0, $store->putCalls);
    }

    public function test_approve_pending_transitions_under_lock_and_uses_receipt_remaining_ttl(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $store->seedRaw(self::HASH, $this->pendingRecord()->toArray());
        $adapter = new CacheConfirmationStore($store);

        self::assertTrue($adapter->approvePending(self::HASH, 120, 240));
        $saved = $store->rawRecord(self::HASH);
        self::assertSame('approved', $saved['state']);
        self::assertSame(240, $saved['receiptExpiresAt']);
        self::assertSame(120, $store->lastPutSeconds);
        $this->assertMutationWasInsideExactTokenLock($store, 'put');
    }

    public function test_consume_is_locked_scope_mismatch_does_not_spend_and_exact_scope_deletes_before_success(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $approved = new ConfirmationRecord(
            state: ConfirmationRecordState::Approved,
            scopeFingerprint: str_repeat('d', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 400,
            receiptExpiresAt: 240,
        );
        $store->seedRaw(self::HASH, $approved->toArray());
        $adapter = new CacheConfirmationStore($store);

        self::assertFalse($adapter->consumeApproved(self::HASH, str_repeat('e', 64), 150));
        self::assertNotNull($store->rawRecord(self::HASH), 'Scope mismatch must leave the approved receipt intact.');
        self::assertSame(0, $store->forgetCalls);
        $this->assertMutationWasInsideExactTokenLock($store, null);

        $store->resetLog();
        self::assertTrue($adapter->consumeApproved(self::HASH, str_repeat('d', 64), 150));
        self::assertNull($store->rawRecord(self::HASH));
        self::assertSame(1, $store->forgetCalls);
        $this->assertMutationWasInsideExactTokenLock($store, 'forget');
    }

    public function test_consume_refuses_pending_and_expired_receipts_at_equality(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $adapter = new CacheConfirmationStore($store);

        $store->seedRaw(self::HASH, $this->pendingRecord()->toArray());
        self::assertFalse($adapter->consumeApproved(self::HASH, str_repeat('b', 64), 120));
        self::assertSame(0, $store->forgetCalls);

        $approved = new ConfirmationRecord(
            state: ConfirmationRecordState::Approved,
            scopeFingerprint: str_repeat('b', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 400,
            receiptExpiresAt: 220,
        );
        $store->seedRaw(self::HASH, $approved->toArray());
        $store->resetLog();
        self::assertFalse($adapter->consumeApproved(self::HASH, str_repeat('b', 64), 220));
        self::assertNotNull($store->rawRecord(self::HASH));
        self::assertSame(0, $store->forgetCalls);
    }

    public function test_lock_acquisition_failure_throws_without_any_unlocked_cache_mutation(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $store->lockCanAcquire = false;
        $adapter = new CacheConfirmationStore($store);

        try {
            $adapter->createPending(self::HASH, $this->pendingRecord(), 300);
            self::fail('Expected lock acquisition failure.');
        } catch (ConfirmationStoreUnavailable $exception) {
            self::assertStringNotContainsString(self::HASH, $exception->getMessage());
        }

        self::assertSame('lock.block:2', $store->operations[1] ?? null);
        self::assertSame(0, $store->getCalls);
        self::assertSame(0, $store->putCalls);
        self::assertSame(0, $store->forgetCalls);
    }

    public function test_failed_cache_write_or_delete_fails_closed_instead_of_claiming_authority(): void
    {
        $this->requireTypes();
        $store = new LockingCacheStore();
        $store->putSucceeds = false;
        $adapter = new CacheConfirmationStore($store);

        $this->expectException(ConfirmationStoreUnavailable::class);
        $adapter->createPending(self::HASH, $this->pendingRecord(), 300);
    }

    private function requireTypes(): void
    {
        $this->test_cache_store_types_exist();
    }

    private function pendingRecord(): ConfirmationRecord
    {
        return new ConfirmationRecord(
            state: ConfirmationRecordState::Pending,
            scopeFingerprint: str_repeat('b', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 400,
        );
    }

    private function assertMutationWasInsideExactTokenLock(LockingCacheStore $store, ?string $mutation): void
    {
        $lock = 'lock:surfacerelay:confirmation:lock:' . self::HASH;
        self::assertSame($lock, $store->operations[0] ?? null);
        self::assertSame('lock.block:2', $store->operations[1] ?? null);
        self::assertSame('lock.release', $store->operations[array_key_last($store->operations)] ?? null);

        if ($mutation !== null) {
            $mutationIndex = array_search($mutation, $store->operations, true);
            $acquireIndex = array_search('lock.block:2', $store->operations, true);
            $releaseIndex = array_search('lock.release', $store->operations, true);
            self::assertIsInt($mutationIndex);
            self::assertIsInt($acquireIndex);
            self::assertIsInt($releaseIndex);
            self::assertGreaterThan($acquireIndex, $mutationIndex);
            self::assertLessThan($releaseIndex, $mutationIndex);
        }
    }
}

final class NonLockingCacheStore implements Store
{
    public function get($key) { return null; }
    public function many(array $keys) { return []; }
    public function put($key, $value, $seconds) { return true; }
    public function putMany(array $values, $seconds) { return true; }
    public function increment($key, $value = 1) { return false; }
    public function decrement($key, $value = 1) { return false; }
    public function forever($key, $value) { return true; }
    public function touch($key, $seconds) { return true; }
    public function forget($key) { return true; }
    public function flush() { return true; }
    public function getPrefix() { return ''; }
}

final class LockingCacheStore implements Store, LockProvider
{
    /** @var array<string, mixed> */
    private array $values = [];

    /** @var list<string> */
    public array $operations = [];

    public bool $lockCanAcquire = true;
    public bool $putSucceeds = true;
    public bool $forgetSucceeds = true;
    public int $getCalls = 0;
    public int $putCalls = 0;
    public int $forgetCalls = 0;
    public ?string $lastPutKey = null;
    public mixed $lastPutValue = null;
    public ?int $lastPutSeconds = null;
    public ?TestCacheLock $lastLock = null;

    public function get($key)
    {
        $this->getCalls++;
        $this->operations[] = 'get';
        return $this->values[$key] ?? null;
    }

    public function many(array $keys)
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->values[$key] ?? null;
        }
        return $result;
    }

    public function put($key, $value, $seconds)
    {
        $this->putCalls++;
        $this->operations[] = 'put';
        $this->lastPutKey = $key;
        $this->lastPutValue = $value;
        $this->lastPutSeconds = $seconds;
        if (!$this->putSucceeds) {
            return false;
        }
        $this->values[$key] = $value;
        return true;
    }

    public function putMany(array $values, $seconds) { return true; }
    public function increment($key, $value = 1) { return false; }
    public function decrement($key, $value = 1) { return false; }
    public function forever($key, $value) { return true; }
    public function touch($key, $seconds) { return true; }

    public function forget($key)
    {
        $this->forgetCalls++;
        $this->operations[] = 'forget';
        if (!$this->forgetSucceeds) {
            return false;
        }
        unset($this->values[$key]);
        return true;
    }

    public function flush() { $this->values = []; return true; }
    public function getPrefix() { return ''; }

    public function lock($name, $seconds = 0, $owner = null)
    {
        $this->operations[] = 'lock:' . $name;
        return $this->lastLock = new TestCacheLock($this->operations, $this->lockCanAcquire);
    }

    public function restoreLock($name, $owner)
    {
        return new TestCacheLock($this->operations, $this->lockCanAcquire);
    }

    /** @param array<string, mixed> $raw */
    public function seedRaw(string $tokenHash, array $raw): void
    {
        $this->values['surfacerelay:confirmation:record:' . $tokenHash] = $raw;
    }

    /** @return array<string, mixed>|null */
    public function rawRecord(string $tokenHash): ?array
    {
        $value = $this->values['surfacerelay:confirmation:record:' . $tokenHash] ?? null;
        return is_array($value) ? $value : null;
    }

    public function resetLog(): void
    {
        $this->operations = [];
        $this->getCalls = 0;
        $this->putCalls = 0;
        $this->forgetCalls = 0;
        $this->lastPutKey = null;
        $this->lastPutValue = null;
        $this->lastPutSeconds = null;
        $this->lastLock = null;
    }
}

final class TestCacheLock implements Lock
{
    public int $releaseCalls = 0;

    /** @var list<string> */
    private array $operations;

    /** @param list<string> $operations */
    public function __construct(array &$operations, private readonly bool $canAcquire)
    {
        $this->operations = &$operations;
    }

    public function get($callback = null)
    {
        $this->operations[] = 'lock.get';
        if (!$this->canAcquire) {
            return false;
        }
        if ($callback !== null) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }
        return true;
    }

    public function block($seconds, $callback = null)
    {
        $this->operations[] = 'lock.block:' . $seconds;
        if (!$this->canAcquire) {
            throw new LockTimeoutException();
        }
        if ($callback !== null) {
            try {
                return $callback();
            } finally {
                $this->release();
            }
        }
        return true;
    }

    public function release()
    {
        $this->releaseCalls++;
        $this->operations[] = 'lock.release';
        return true;
    }

    public function owner()
    {
        return 'test-owner';
    }

    public function forceRelease()
    {
        $this->operations[] = 'lock.forceRelease';
    }
}
