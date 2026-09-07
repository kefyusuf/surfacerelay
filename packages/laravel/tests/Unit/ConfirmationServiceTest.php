<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerationFailed;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Confirmation\RandomConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Confirmation\SystemConfirmationClock;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

final class ConfirmationServiceTest extends TestCase
{
    private const string TOKEN_A = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    private const string TOKEN_B = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';
    private const string TOKEN_C = 'CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC';
    private const string TOKEN_D = 'DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD';

    public function test_confirmation_types_exist(): void
    {
        foreach ([
            ConfirmationRecordState::class,
            ConfirmationRecord::class,
            ConfirmationStore::class,
            ConfirmationClock::class,
            SystemConfirmationClock::class,
            ConfirmationTokenGenerator::class,
            RandomConfirmationTokenGenerator::class,
            ConfirmationTokenGenerationFailed::class,
            ConfirmationService::class,
        ] as $type) {
            self::assertTrue(
                class_exists($type) || interface_exists($type) || enum_exists($type),
                sprintf('Expected confirmation type %s to exist.', $type),
            );
        }
    }

    public function test_issue_creates_opaque_pending_challenge_without_storing_raw_token(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $tokens = new QueueConfirmationTokenGenerator([self::TOKEN_A]);
        $service = new ConfirmationService($store, $clock, $tokens);

        $challenge = $service->issueChallenge('scope-hash-1', 'Approve refund');

        self::assertInstanceOf(ConfirmationChallenge::class, $challenge);
        self::assertSame(self::TOKEN_A, $challenge->challengeId);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $challenge->challengeId);
        self::assertSame('Approve refund', $challenge->summary);
        self::assertSame('2026-09-07T12:05:00Z', $challenge->expiresAt);
        self::assertSame(1, $store->createPendingCalls);

        $expectedHash = hash('sha256', self::TOKEN_A);
        self::assertSame($expectedHash, $store->lastCreateTokenHash);
        self::assertNotSame(self::TOKEN_A, $store->lastCreateTokenHash);
        self::assertSame(300, $store->lastCreateTtlSeconds);
        self::assertSame(ConfirmationRecordState::Pending, $store->lastCreatedRecord?->state);
        self::assertSame('scope-hash-1', $store->lastCreatedRecord?->scopeFingerprint);
        self::assertSame($clock->now(), $store->lastCreatedRecord?->issuedAt);
        self::assertSame($clock->now() + 300, $store->lastCreatedRecord?->challengeExpiresAt);
        self::assertNull($store->lastCreatedRecord?->receiptExpiresAt);
        self::assertStringNotContainsString(self::TOKEN_A, json_encode($store->lastCreatedRecord?->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_collision_retries_with_new_tokens_and_is_bounded_to_three_attempts(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $tokens = new QueueConfirmationTokenGenerator([self::TOKEN_A, self::TOKEN_B, self::TOKEN_C, self::TOKEN_D]);

        $store->rejectCreateHashes = [
            hash('sha256', self::TOKEN_A),
            hash('sha256', self::TOKEN_B),
        ];

        $challenge = (new ConfirmationService($store, $clock, $tokens))
            ->issueChallenge('scope-hash', 'Approve refund');

        self::assertSame(self::TOKEN_C, $challenge->challengeId);
        self::assertSame(3, $store->createPendingCalls);
        self::assertSame(3, $tokens->calls);

        $failingStore = new RecordingConfirmationStore();
        $failingStore->rejectAllCreates = true;
        $failingTokens = new QueueConfirmationTokenGenerator([self::TOKEN_A, self::TOKEN_B, self::TOKEN_C, self::TOKEN_D]);
        $service = new ConfirmationService($failingStore, $clock, $failingTokens);

        try {
            $service->issueChallenge('scope-hash', 'Approve refund');
            self::fail('Expected bounded collision failure.');
        } catch (ConfirmationTokenGenerationFailed $exception) {
            self::assertSame(3, $failingStore->createPendingCalls);
            self::assertSame(3, $failingTokens->calls);
            foreach ([self::TOKEN_A, self::TOKEN_B, self::TOKEN_C] as $secret) {
                self::assertStringNotContainsString($secret, $exception->getMessage());
            }
        }
    }

    public function test_approval_transitions_exact_pending_token_and_returns_same_opaque_receipt(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $challenge = $service->issueChallenge('scope-hash', 'Approve refund');

        $clock->advance(30);
        $receipt = $service->approveChallenge($challenge->challengeId);

        self::assertSame(self::TOKEN_A, $receipt);
        self::assertSame(hash('sha256', self::TOKEN_A), $store->lastApproveTokenHash);
        self::assertSame($clock->now(), $store->lastApproveNow);
        self::assertSame($clock->now() + 120, $store->lastApproveReceiptExpiresAt);
        self::assertSame(ConfirmationRecordState::Approved, $store->recordForToken(self::TOKEN_A)?->state);
        self::assertSame($clock->now() + 120, $store->recordForToken(self::TOKEN_A)?->receiptExpiresAt);

        $originalExpiry = $store->recordForToken(self::TOKEN_A)?->receiptExpiresAt;
        $clock->advance(5);
        self::assertNull($service->approveChallenge($challenge->challengeId));
        self::assertSame($originalExpiry, $store->recordForToken(self::TOKEN_A)?->receiptExpiresAt,
            'Repeat approval must not reset or extend receipt expiry.');
    }

    public function test_unknown_pending_as_receipt_and_expired_challenge_never_grant_authority(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $challenge = $service->issueChallenge('scope-hash', 'Approve refund');

        self::assertNull($service->approveChallenge(self::TOKEN_B));
        self::assertFalse($service->consumeReceipt($challenge->challengeId, 'scope-hash'),
            'A pending challenge token is not receipt authority.');

        $clock->set(strtotime('2026-09-07T12:05:00Z'));
        self::assertNull($service->approveChallenge($challenge->challengeId),
            'Challenge is expired at exact equality with challengeExpiresAt.');
    }

    public function test_exact_approved_receipt_consumes_once_and_replay_fails(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $challenge = $service->issueChallenge('scope-hash', 'Approve refund');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertSame(self::TOKEN_A, $receipt);

        self::assertTrue($service->consumeReceipt($receipt, 'scope-hash'));
        self::assertFalse($service->consumeReceipt($receipt, 'scope-hash'));
        self::assertSame(2, $store->consumeApprovedCalls);
        self::assertNull($store->recordForToken(self::TOKEN_A), 'Successful consume must irreversibly spend the record.');
    }

    public function test_scope_mismatch_fails_without_spending_valid_receipt(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $challenge = $service->issueChallenge('scope-hash', 'Approve refund');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertSame(self::TOKEN_A, $receipt);

        self::assertFalse($service->consumeReceipt($receipt, 'wrong-scope'));
        self::assertNotNull($store->recordForToken(self::TOKEN_A),
            'Wrong-scope attempt must not consume an otherwise-valid receipt.');
        self::assertTrue($service->consumeReceipt($receipt, 'scope-hash'));
    }

    public function test_receipt_is_expired_at_exact_equality(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $challenge = $service->issueChallenge('scope-hash', 'Approve refund');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertSame(self::TOKEN_A, $receipt);

        $clock->advance(120);
        self::assertFalse($service->consumeReceipt($receipt, 'scope-hash'));
        self::assertNotNull($store->recordForToken(self::TOKEN_A));
    }

    public function test_non_positive_ttls_fail_before_any_store_operation(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));

        foreach ([[0, 120], [-1, 120], [300, 0], [300, -1]] as [$challengeTtl, $receiptTtl]) {
            $store = new RecordingConfirmationStore();
            try {
                new ConfirmationService(
                    $store,
                    $clock,
                    new QueueConfirmationTokenGenerator([self::TOKEN_A]),
                    challengeTtlSeconds: $challengeTtl,
                    receiptTtlSeconds: $receiptTtl,
                );
                self::fail('Expected invalid TTL configuration to fail.');
            } catch (\InvalidArgumentException) {
                self::assertSame(0, $store->totalOperations());
            }
        }
    }

    public function test_malformed_candidates_fail_closed_without_store_lookup_or_secret_echo(): void
    {
        $this->requireTypes();
        $clock = new MutableConfirmationClock(strtotime('2026-09-07T12:00:00Z'));
        $store = new RecordingConfirmationStore();
        $service = new ConfirmationService($store, $clock, new QueueConfirmationTokenGenerator([self::TOKEN_A]));
        $secret = 'not-valid!!receipt-secret';

        self::assertNull($service->approveChallenge($secret));
        self::assertFalse($service->consumeReceipt($secret, 'scope-hash'));
        self::assertSame(0, $store->approvePendingCalls);
        self::assertSame(0, $store->consumeApprovedCalls);
    }

    public function test_random_generator_emits_32_byte_unpadded_base64url_token(): void
    {
        $this->requireTypes();
        $token = (new RandomConfirmationTokenGenerator())->generate();

        self::assertSame(43, strlen($token));
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $token);
        self::assertStringNotContainsString('=', $token);
    }

    public function test_confirmation_record_round_trips_cache_safe_state_and_rejects_corrupt_combinations(): void
    {
        $this->requireTypes();
        $record = new ConfirmationRecord(
            state: ConfirmationRecordState::Approved,
            scopeFingerprint: str_repeat('a', 64),
            summary: 'Approve refund',
            issuedAt: 100,
            challengeExpiresAt: 400,
            receiptExpiresAt: 220,
        );

        $array = $record->toArray();
        self::assertSame($record->state, ConfirmationRecord::fromArray($array)->state);
        self::assertSame(str_repeat('a', 64), $array['scopeFingerprint']);
        self::assertSame('approved', $array['state']);
        self::assertContainsOnly('string|int|null', array_values($array));

        $this->expectException(\UnexpectedValueException::class);
        ConfirmationRecord::fromArray([
            'state' => 'pending',
            'scopeFingerprint' => str_repeat('a', 64),
            'summary' => 'Approve refund',
            'issuedAt' => 100,
            'challengeExpiresAt' => 400,
            'receiptExpiresAt' => 220,
        ]);
    }

    private function requireTypes(): void
    {
        $this->test_confirmation_types_exist();
    }
}

if (
    interface_exists(ConfirmationStore::class)
    && interface_exists(ConfirmationClock::class)
    && interface_exists(ConfirmationTokenGenerator::class)
    && class_exists(ConfirmationRecord::class)
    && enum_exists(ConfirmationRecordState::class)
) {
    final class MutableConfirmationClock implements ConfirmationClock
    {
        public function __construct(private int $timestamp) {}

        public function now(): int
        {
            return $this->timestamp;
        }

        public function advance(int $seconds): void
        {
            $this->timestamp += $seconds;
        }

        public function set(int $timestamp): void
        {
            $this->timestamp = $timestamp;
        }
    }

    final class QueueConfirmationTokenGenerator implements ConfirmationTokenGenerator
    {
        public int $calls = 0;

        /** @param list<string> $tokens */
        public function __construct(private array $tokens) {}

        public function generate(): string
        {
            $token = $this->tokens[$this->calls] ?? throw new \RuntimeException('Fake token queue exhausted.');
            $this->calls++;
            return $token;
        }
    }

    final class RecordingConfirmationStore implements ConfirmationStore
    {
        /** @var array<string, ConfirmationRecord> */
        private array $records = [];

        /** @var list<string> */
        public array $rejectCreateHashes = [];

        public bool $rejectAllCreates = false;
        public int $createPendingCalls = 0;
        public int $approvePendingCalls = 0;
        public int $consumeApprovedCalls = 0;
        public ?string $lastCreateTokenHash = null;
        public ?ConfirmationRecord $lastCreatedRecord = null;
        public ?int $lastCreateTtlSeconds = null;
        public ?string $lastApproveTokenHash = null;
        public ?int $lastApproveNow = null;
        public ?int $lastApproveReceiptExpiresAt = null;

        public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
        {
            $this->createPendingCalls++;
            $this->lastCreateTokenHash = $tokenHash;
            $this->lastCreatedRecord = $record;
            $this->lastCreateTtlSeconds = $ttlSeconds;

            if ($this->rejectAllCreates || in_array($tokenHash, $this->rejectCreateHashes, true) || isset($this->records[$tokenHash])) {
                return false;
            }

            $this->records[$tokenHash] = $record;
            return true;
        }

        public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
        {
            $this->approvePendingCalls++;
            $this->lastApproveTokenHash = $tokenHash;
            $this->lastApproveNow = $now;
            $this->lastApproveReceiptExpiresAt = $receiptExpiresAt;

            $record = $this->records[$tokenHash] ?? null;
            if ($record === null || $record->state !== ConfirmationRecordState::Pending || $now >= $record->challengeExpiresAt) {
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
            $this->consumeApprovedCalls++;
            $record = $this->records[$tokenHash] ?? null;
            if (
                $record === null
                || $record->state !== ConfirmationRecordState::Approved
                || $record->receiptExpiresAt === null
                || $now >= $record->receiptExpiresAt
                || !hash_equals($record->scopeFingerprint, $expectedScopeFingerprint)
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

        public function totalOperations(): int
        {
            return $this->createPendingCalls + $this->approvePendingCalls + $this->consumeApprovedCalls;
        }
    }
}
