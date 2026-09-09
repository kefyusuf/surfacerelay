<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlanKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStage;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class IdempotencyStageTest extends TestCase
{
    private const string RAW_KEY = 'refund-request-42';

    public function test_stage_and_error_vocabulary_are_available(): void
    {
        self::assertTrue(class_exists(IdempotencyStage::class), 'IdempotencyStage must exist.');
        self::assertSame('idempotency_key_required', CoreActionErrorCode::IDEMPOTENCY_KEY_REQUIRED);
        self::assertSame('idempotency_key_invalid', CoreActionErrorCode::IDEMPOTENCY_KEY_INVALID);
        self::assertSame('idempotency_conflict', CoreActionErrorCode::IDEMPOTENCY_CONFLICT);
        self::assertSame('idempotency_in_progress', CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS);
        self::assertSame('idempotency_indeterminate', CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE);
    }

    public function test_none_policy_ignores_supplied_key_and_never_touches_store(): void
    {
        [$stage, $store] = $this->harness();
        $state = $this->state(IdempotencyPolicy::None, self::RAW_KEY);

        $decision = $stage->process($state);

        $this->assertContinueWithPlan($decision, IdempotencyExecutionPlanKind::Bypass);
        self::assertSame(0, $store->findCalls);
    }

    public function test_recommended_policy_without_key_bypasses_and_never_touches_store(): void
    {
        [$stage, $store] = $this->harness();
        $state = $this->state(IdempotencyPolicy::RecommendedKey, null);

        $decision = $stage->process($state);

        $this->assertContinueWithPlan($decision, IdempotencyExecutionPlanKind::Bypass);
        self::assertSame(0, $store->findCalls);
    }

    public function test_required_policy_without_key_halts_before_store_access(): void
    {
        [$stage, $store] = $this->harness();

        $decision = $stage->process($this->state(IdempotencyPolicy::RequiredKey, null));

        $this->assertHalt($decision, 'idempotency_key_required');
        self::assertSame(0, $store->findCalls);
    }

    public function test_invalid_non_null_keys_halt_for_recommended_and_required_policies(): void
    {
        foreach ([IdempotencyPolicy::RecommendedKey, IdempotencyPolicy::RequiredKey] as $policy) {
            foreach (['', str_repeat('x', 241)] as $invalidKey) {
                [$stage, $store] = $this->harness();

                $decision = $stage->process($this->state($policy, $invalidKey));

                $this->assertHalt($decision, 'idempotency_key_invalid');
                self::assertSame(0, $store->findCalls);
            }
        }
    }

    public function test_fresh_preflight_materializes_fresh_attempt_plan(): void
    {
        [$stage, $store] = $this->harness();
        $state = $this->state(IdempotencyPolicy::RequiredKey, self::RAW_KEY);

        $decision = $stage->process($state);

        $this->assertContinueWithPlan($decision, IdempotencyExecutionPlanKind::FreshAttempt);
        self::assertSame(1, $store->findCalls);
        self::assertSame(
            (new IdempotencyKeyHasher())->hash($state, self::RAW_KEY),
            $decision->state->idempotencyPlan?->keyHash,
        );
        self::assertSame(
            (new IdempotencyIntentHasher())->fingerprint($state),
            $decision->state->idempotencyPlan?->intentFingerprint,
        );
        self::assertFalse($decision->state->hasOutput);
    }

    public function test_completed_exact_preflight_materializes_replay_plan_and_raw_output(): void
    {
        [$stage, $store] = $this->harness();
        $state = $this->state(IdempotencyPolicy::RequiredKey, self::RAW_KEY);
        $keyHash = (new IdempotencyKeyHasher())->hash($state, self::RAW_KEY);
        $intent = (new IdempotencyIntentHasher())->fingerprint($state);
        $store->records[$keyHash] = new IdempotencyRecord(
            $keyHash,
            $intent,
            IdempotencyRecordState::Completed,
            '{"done":true}',
            50,
            150,
        );

        $decision = $stage->process($state);

        $this->assertContinueWithPlan($decision, IdempotencyExecutionPlanKind::Replay);
        self::assertSame(['done' => true], $decision->state->idempotencyPlan?->replayOutput);
        self::assertTrue($decision->state->hasOutput);
        self::assertSame(['done' => true], $decision->state->output);
    }

    public function test_completed_mismatched_intent_halts_as_conflict_without_details(): void
    {
        [$stage, $store] = $this->harness();
        $state = $this->state(IdempotencyPolicy::RequiredKey, self::RAW_KEY);
        $keyHash = (new IdempotencyKeyHasher())->hash($state, self::RAW_KEY);
        $store->records[$keyHash] = new IdempotencyRecord(
            $keyHash,
            str_repeat('f', 64),
            IdempotencyRecordState::Completed,
            '{"done":true}',
            50,
            150,
        );

        $this->assertHalt($stage->process($state), 'idempotency_conflict');
    }

    public function test_active_in_progress_and_indeterminate_preflights_halt_fail_closed(): void
    {
        foreach ([
            [IdempotencyRecordState::InProgress, 'idempotency_in_progress'],
            [IdempotencyRecordState::Indeterminate, 'idempotency_indeterminate'],
        ] as [$recordState, $expectedCode]) {
            [$stage, $store] = $this->harness();
            $state = $this->state(IdempotencyPolicy::RequiredKey, self::RAW_KEY);
            $keyHash = (new IdempotencyKeyHasher())->hash($state, self::RAW_KEY);
            $intent = (new IdempotencyIntentHasher())->fingerprint($state);
            $store->records[$keyHash] = new IdempotencyRecord(
                $keyHash,
                $intent,
                $recordState,
                null,
                50,
                150,
            );

            $this->assertHalt($stage->process($state), $expectedCode);
        }
    }

    public function test_all_state_copy_methods_preserve_idempotency_plan(): void
    {
        self::assertTrue(
            property_exists(ActionPipelineState::class, 'idempotencyPlan'),
            'ActionPipelineState must expose typed idempotency plan state.',
        );
        $state = $this->state(IdempotencyPolicy::RequiredKey, self::RAW_KEY);
        $plan = IdempotencyExecutionPlan::fresh(str_repeat('a', 64), str_repeat('b', 64));
        $state = $state->withIdempotencyPlan($plan);

        self::assertSame($plan, $state->withInput(['normalized' => true])->idempotencyPlan);
        self::assertSame($plan, $state->withOutput(['ok' => true])->idempotencyPlan);
        self::assertSame(
            $plan,
            $state->withContext(new InvocationContext('webmcp', 'corr-2', idempotencyKey: self::RAW_KEY))->idempotencyPlan,
        );
    }

    /** @return array{IdempotencyStage, object} */
    private function harness(): array
    {
        self::assertTrue(
            class_exists(IdempotencyStage::class),
            'IdempotencyStage must exist before stage behavior can pass.',
        );

        $store = new class implements IdempotencyStore {
            /** @var array<string, IdempotencyRecord> */
            public array $records = [];
            public int $findCalls = 0;

            public function find(string $keyHash): ?IdempotencyRecord
            {
                $this->findCalls++;
                return $this->records[$keyHash] ?? null;
            }

            public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult
            {
                throw new \LogicException('Preflight stage must not claim execution ownership.');
            }

            public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void
            {
                throw new \LogicException('Preflight stage must not complete execution.');
            }

            public function markIndeterminate(string $keyHash, string $intentFingerprint): void
            {
                throw new \LogicException('Preflight stage must not mutate execution state.');
            }
        };
        $clock = new class implements IdempotencyClock {
            public function now(): int { return 100; }
        };
        $service = new IdempotencyService($store, $clock, new IdempotencyReplayCodec());

        return [
            new IdempotencyStage(
                new IdempotencyKeyValidator(),
                new IdempotencyKeyHasher(),
                new IdempotencyIntentHasher(),
                $service,
            ),
            $store,
        ];
    }

    private function state(IdempotencyPolicy $policy, ?string $key): ActionPipelineState
    {
        return new ActionPipelineState(
            definition: new ActionDefinition(
                id: 'orders.refund.prepare',
                version: 1,
                title: 'Prepare refund',
                description: 'Creates a refund draft.',
                inputSchema: ['type' => 'object'],
                scope: ActionScope::PageScoped,
                effect: ActionEffect::ReversibleWrite,
                risk: ActionRisk::Moderate,
                idempotency: $policy,
                outputSensitivity: OutputSensitivity::Sensitive,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [],
            ),
            input: ['reason' => 'damaged'],
            context: new InvocationContext(
                surface: 'webmcp',
                correlationId: 'corr-1',
                idempotencyKey: $key,
            ),
        );
    }

    private function assertContinueWithPlan(
        ActionPipelineDecision $decision,
        IdempotencyExecutionPlanKind $kind,
    ): void {
        self::assertTrue($decision->continue);
        self::assertNull($decision->halt);
        self::assertNotNull($decision->state->idempotencyPlan);
        self::assertSame($kind, $decision->state->idempotencyPlan->kind);
    }

    private function assertHalt(ActionPipelineDecision $decision, string $code): void
    {
        self::assertFalse($decision->continue);
        self::assertNotNull($decision->halt);
        self::assertSame($code, $decision->halt->code);
        self::assertNull($decision->halt->details);
        self::assertNull($decision->halt->confirmation);
    }
}
