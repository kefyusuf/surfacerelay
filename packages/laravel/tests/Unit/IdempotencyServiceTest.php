<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Idempotency\CorruptIdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClaimKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyConfigurationViolation;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Idempotency\IdempotencyPreflightKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;

final class IdempotencyServiceTest extends TestCase
{
    private const string KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string INTENT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string OTHER_INTENT = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function test_state_machine_types_are_available(): void
    {
        foreach ([
            IdempotencyRecord::class,
            IdempotencyService::class,
            IdempotencyExecutionPlan::class,
            IdempotencyStoreClaimResult::class,
            IdempotencyPreflightKind::class,
            IdempotencyClaimKind::class,
        ] as $type) {
            self::assertTrue(class_exists($type) || interface_exists($type), $type . ' must exist.');
        }
        self::assertTrue(interface_exists(IdempotencyStore::class));
        self::assertTrue(interface_exists(IdempotencyClock::class));
    }

    public function test_record_invariants_fail_closed(): void
    {
        $this->assertTypesAvailable();

        foreach ([
            fn () => new IdempotencyRecord('bad', self::INTENT, IdempotencyRecordState::InProgress, null, 10, 20),
            fn () => new IdempotencyRecord(self::KEY, self::INTENT, IdempotencyRecordState::InProgress, null, 10, 10),
            fn () => new IdempotencyRecord(self::KEY, self::INTENT, IdempotencyRecordState::Completed, null, 10, 20),
            fn () => new IdempotencyRecord(self::KEY, self::INTENT, IdempotencyRecordState::Indeterminate, '{}', 10, 20),
        ] as $factory) {
            try {
                $factory();
                self::fail('Invalid idempotency record must fail closed.');
            } catch (CorruptIdempotencyRecord) {
                self::assertTrue(true);
            }
        }
    }

    public function test_preflight_classifies_missing_expired_exact_and_conflicting_records(): void
    {
        [$service, $store, $clock] = $this->harness(100);

        self::assertSame(IdempotencyPreflightKind::Fresh, $service->preflight(self::KEY, self::INTENT)->kind);

        $store->records[self::KEY] = $this->record(IdempotencyRecordState::Completed, self::INTENT, 50, 150, '{"ok":true}');
        $replay = $service->preflight(self::KEY, self::INTENT);
        self::assertSame(IdempotencyPreflightKind::Replay, $replay->kind);
        self::assertSame(['ok' => true], $replay->output);
        self::assertSame(150, $store->records[self::KEY]->expiresAt, 'Replay must not extend retention.');

        self::assertSame(
            IdempotencyPreflightKind::Conflict,
            $service->preflight(self::KEY, self::OTHER_INTENT)->kind,
        );

        $store->records[self::KEY] = $this->record(IdempotencyRecordState::InProgress, self::INTENT, 50, 150);
        self::assertSame(IdempotencyPreflightKind::InProgress, $service->preflight(self::KEY, self::INTENT)->kind);

        $store->records[self::KEY] = $this->record(IdempotencyRecordState::Indeterminate, self::INTENT, 50, 150);
        self::assertSame(IdempotencyPreflightKind::Indeterminate, $service->preflight(self::KEY, self::INTENT)->kind);

        $store->records[self::KEY] = $this->record(IdempotencyRecordState::Completed, self::INTENT, 50, 100, '{"old":true}');
        $clock->time = 100;
        self::assertSame(IdempotencyPreflightKind::Fresh, $service->preflight(self::KEY, self::INTENT)->kind);
    }

    public function test_fresh_claim_uses_current_clock_and_default_retention(): void
    {
        [$service, $store] = $this->harness(1_000);
        $plan = IdempotencyExecutionPlan::fresh(self::KEY, self::INTENT);

        $result = $service->claim($plan);

        self::assertSame(IdempotencyClaimKind::Claimed, $result->kind);
        self::assertSame(1, $store->claimCalls);
        self::assertSame(1_000, $store->records[self::KEY]->createdAt);
        self::assertSame(87_400, $store->records[self::KEY]->expiresAt);
        self::assertSame(IdempotencyRecordState::InProgress, $store->records[self::KEY]->state);
    }

    public function test_claim_race_maps_existing_record_without_second_ownership(): void
    {
        foreach ([
            [IdempotencyRecordState::Completed, self::INTENT, IdempotencyClaimKind::Replay, '{"done":true}'],
            [IdempotencyRecordState::Completed, self::OTHER_INTENT, IdempotencyClaimKind::Conflict, '{"done":true}'],
            [IdempotencyRecordState::InProgress, self::INTENT, IdempotencyClaimKind::InProgress, null],
            [IdempotencyRecordState::Indeterminate, self::INTENT, IdempotencyClaimKind::Indeterminate, null],
        ] as [$state, $intent, $expected, $payload]) {
            [$service, $store] = $this->harness(100);
            $store->nextClaimRecord = $this->record($state, $intent, 50, 150, $payload);

            $result = $service->claim(IdempotencyExecutionPlan::fresh(self::KEY, self::INTENT));

            self::assertSame($expected, $result->kind);
            if ($expected === IdempotencyClaimKind::Replay) {
                self::assertSame(['done' => true], $result->output);
            }
            self::assertSame(1, $store->claimCalls);
        }
    }

    public function test_complete_and_indeterminate_transitions_require_fresh_plan(): void
    {
        [$service, $store] = $this->harness(100);
        $fresh = IdempotencyExecutionPlan::fresh(self::KEY, self::INTENT);
        self::assertSame(IdempotencyClaimKind::Claimed, $service->claim($fresh)->kind);

        $service->complete($fresh, ['ok' => true]);
        self::assertSame(IdempotencyRecordState::Completed, $store->records[self::KEY]->state);
        self::assertSame(['ok' => true], (new IdempotencyReplayCodec())->decode($store->records[self::KEY]->outputPayload));

        [$service2, $store2] = $this->harness(200);
        $fresh2 = IdempotencyExecutionPlan::fresh(self::KEY, self::INTENT);
        $service2->claim($fresh2);
        $service2->markIndeterminate($fresh2);
        self::assertSame(IdempotencyRecordState::Indeterminate, $store2->records[self::KEY]->state);

        foreach ([IdempotencyExecutionPlan::bypass(), IdempotencyExecutionPlan::replay(self::KEY, self::INTENT, ['x' => 1])] as $wrong) {
            try {
                $service->complete($wrong, null);
                self::fail('Only FreshAttempt plan may complete.');
            } catch (IdempotencyConfigurationViolation) {
                self::assertTrue(true);
            }
            try {
                $service->markIndeterminate($wrong);
                self::fail('Only FreshAttempt plan may become indeterminate.');
            } catch (IdempotencyConfigurationViolation) {
                self::assertTrue(true);
            }
        }
    }

    public function test_non_positive_retention_is_rejected(): void
    {
        $this->assertTypesAvailable();
        $store = $this->fakeStore();
        $clock = $this->fakeClock(100);

        foreach ([0, -1] as $retention) {
            try {
                new IdempotencyService($store, $clock, new IdempotencyReplayCodec(), $retention);
                self::fail('Retention must be positive.');
            } catch (IdempotencyConfigurationViolation) {
                self::assertTrue(true);
            }
        }
    }

    /** @return array{IdempotencyService, object, object} */
    private function harness(int $now): array
    {
        $this->assertTypesAvailable();
        $store = $this->fakeStore();
        $clock = $this->fakeClock($now);

        return [new IdempotencyService($store, $clock, new IdempotencyReplayCodec()), $store, $clock];
    }

    private function record(
        IdempotencyRecordState $state,
        string $intent,
        int $createdAt,
        int $expiresAt,
        ?string $payload = null,
    ): IdempotencyRecord {
        return new IdempotencyRecord(self::KEY, $intent, $state, $payload, $createdAt, $expiresAt);
    }

    private function fakeClock(int $now): object
    {
        return new class($now) implements IdempotencyClock {
            public function __construct(public int $time) {}
            public function now(): int { return $this->time; }
        };
    }

    private function fakeStore(): object
    {
        return new class implements IdempotencyStore {
            /** @var array<string, IdempotencyRecord> */
            public array $records = [];
            public ?IdempotencyRecord $nextClaimRecord = null;
            public int $claimCalls = 0;

            public function find(string $keyHash): ?IdempotencyRecord
            {
                return $this->records[$keyHash] ?? null;
            }

            public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult
            {
                $this->claimCalls++;
                if ($this->nextClaimRecord !== null) {
                    return IdempotencyStoreClaimResult::existing($this->nextClaimRecord);
                }

                $existing = $this->records[$fresh->keyHash] ?? null;
                if ($existing !== null && $existing->isActiveAt($now)) {
                    return IdempotencyStoreClaimResult::existing($existing);
                }

                $this->records[$fresh->keyHash] = $fresh;
                return IdempotencyStoreClaimResult::claimed($fresh);
            }

            public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void
            {
                $record = $this->records[$keyHash];
                if ($record->intentFingerprint !== $intentFingerprint || $record->state !== IdempotencyRecordState::InProgress) {
                    throw new \RuntimeException('test fake impossible completion');
                }
                $this->records[$keyHash] = new IdempotencyRecord(
                    $record->keyHash,
                    $record->intentFingerprint,
                    IdempotencyRecordState::Completed,
                    $outputPayload,
                    $record->createdAt,
                    $record->expiresAt,
                );
            }

            public function markIndeterminate(string $keyHash, string $intentFingerprint): void
            {
                $record = $this->records[$keyHash];
                if ($record->intentFingerprint !== $intentFingerprint || $record->state !== IdempotencyRecordState::InProgress) {
                    throw new \RuntimeException('test fake impossible indeterminate transition');
                }
                $this->records[$keyHash] = new IdempotencyRecord(
                    $record->keyHash,
                    $record->intentFingerprint,
                    IdempotencyRecordState::Indeterminate,
                    null,
                    $record->createdAt,
                    $record->expiresAt,
                );
            }
        };
    }

    private function assertTypesAvailable(): void
    {
        foreach ([
            IdempotencyRecord::class,
            IdempotencyService::class,
            IdempotencyExecutionPlan::class,
            IdempotencyStoreClaimResult::class,
            IdempotencyPreflightKind::class,
            IdempotencyClaimKind::class,
        ] as $type) {
            self::assertTrue(class_exists($type), $type . ' must exist before state-machine behavior can pass.');
        }
        self::assertTrue(interface_exists(IdempotencyStore::class));
        self::assertTrue(interface_exists(IdempotencyClock::class));
    }
}
