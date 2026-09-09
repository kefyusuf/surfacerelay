<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyConfigurationViolation;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;
use SurfaceRelay\Laravel\Idempotency\UnreplayableIdempotencyOutput;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ActionExecutionStageTest extends TestCase
{
    private const string KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string INTENT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';
    private const string OTHER_INTENT = 'cccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccccc';

    public function test_execution_types_exist(): void
    {
        self::assertTrue(interface_exists(ActionExecutor::class), 'ActionExecutor must exist.');
        self::assertTrue(class_exists(ActionExecutionStage::class), 'ActionExecutionStage must exist.');
    }

    public function test_stage_delegates_exact_definition_current_input_and_context_once(): void
    {
        $this->assertTypesExist();
        $definition = $this->definition();
        $context = new InvocationContext('test', 'corr-1');
        $calls = [];

        $executor = new class($calls) implements ActionExecutor {
            /** @param array<int, mixed> $calls */
            public function __construct(private array &$calls) {}

            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                $this->calls[] = [$definition, $input, $context];
                return ['ok' => true];
            }
        };

        $stage = new ActionExecutionStage($executor);
        $state = new ActionPipelineState($definition, ['name' => 'validated'], $context);
        $decision = $stage->process($state);

        self::assertSame(ActionPipelineStage::Execution, $stage->stage());
        self::assertTrue($decision->continue);
        self::assertCount(1, $calls);
        self::assertSame($definition, $calls[0][0]);
        self::assertSame(['name' => 'validated'], $calls[0][1]);
        self::assertSame($context, $calls[0][2]);
        self::assertTrue($decision->state->hasOutput);
        self::assertSame(['ok' => true], $decision->state->output);
    }

    public function test_null_executor_result_still_marks_output_present(): void
    {
        $this->assertTypesExist();
        $executor = new class implements ActionExecutor {
            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                return null;
            }
        };

        $decision = (new ActionExecutionStage($executor))->process(
            new ActionPipelineState($this->definition(), [], new InvocationContext('test', 'corr-null')),
        );

        self::assertTrue($decision->continue);
        self::assertTrue($decision->state->hasOutput);
        self::assertNull($decision->state->output);
    }

    public function test_executor_exception_propagates_unchanged(): void
    {
        $this->assertTypesExist();
        $expected = new \RuntimeException('application failed');
        $executor = new class($expected) implements ActionExecutor {
            public function __construct(private readonly \RuntimeException $exception) {}

            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                throw $this->exception;
            }
        };

        try {
            (new ActionExecutionStage($executor))->process(
                new ActionPipelineState($this->definition(), [], new InvocationContext('test', 'corr-error')),
            );
            self::fail('Expected executor exception to propagate.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }
    }

    public function test_bypass_plan_preserves_old_execution_behavior_without_idempotency_service(): void
    {
        $executor = new ExecutionStageExecutorProbe(['ok' => true]);
        $state = (new ActionPipelineState(
            $this->definition(),
            ['name' => 'validated'],
            new InvocationContext('test', 'corr-bypass'),
        ))->withIdempotencyPlan(IdempotencyExecutionPlan::bypass());

        $decision = (new ActionExecutionStage($executor))->process($state);

        self::assertTrue($decision->continue);
        self::assertSame(1, $executor->calls);
        self::assertSame(['ok' => true], $decision->state->output);
    }

    public function test_fresh_plan_without_idempotency_service_fails_before_executor(): void
    {
        $executor = new ExecutionStageExecutorProbe(['unsafe' => true]);
        $state = $this->freshState();

        try {
            (new ActionExecutionStage($executor))->process($state);
            self::fail('Fresh idempotent execution without a service must fail closed.');
        } catch (IdempotencyConfigurationViolation) {
            self::assertSame(0, $executor->calls);
        }
    }

    public function test_fresh_idempotency_plan_claims_before_executor_and_completes_once(): void
    {
        $executor = new ExecutionStageExecutorProbe(['ok' => true]);
        [$service, $store] = $this->idempotencyHarness();

        $decision = (new ActionExecutionStage($executor, $service))->process($this->freshState());

        self::assertTrue($decision->continue);
        self::assertSame(1, $store->claimCalls, 'Fresh execution must atomically claim ownership before application code.');
        self::assertSame(1, $store->completeCalls);
        self::assertSame(1, $executor->calls);
        self::assertSame(IdempotencyRecordState::Completed, $store->records[self::KEY]->state);
        self::assertSame(['ok' => true], (new IdempotencyReplayCodec())->decode(
            $store->records[self::KEY]->outputPayload,
        ));
        self::assertSame(['ok' => true], $decision->state->output);
    }

    public function test_claim_race_completed_replays_without_executor(): void
    {
        $executor = new ExecutionStageExecutorProbe(['unsafe' => true]);
        [$service, $store] = $this->idempotencyHarness();
        $store->nextClaimRecord = $this->record(
            IdempotencyRecordState::Completed,
            self::INTENT,
            '{"from":"prior"}',
        );

        $decision = (new ActionExecutionStage($executor, $service))->process($this->freshState());

        self::assertTrue($decision->continue);
        self::assertSame(0, $executor->calls);
        self::assertSame(0, $store->completeCalls);
        self::assertSame(['from' => 'prior'], $decision->state->output);
    }

    public function test_claim_race_conflict_in_progress_and_indeterminate_halt_before_executor(): void
    {
        foreach ([
            [IdempotencyRecordState::Completed, self::OTHER_INTENT, CoreActionErrorCode::IDEMPOTENCY_CONFLICT, '{"done":true}'],
            [IdempotencyRecordState::InProgress, self::INTENT, CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS, null],
            [IdempotencyRecordState::Indeterminate, self::INTENT, CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE, null],
        ] as [$recordState, $intent, $expectedCode, $payload]) {
            $executor = new ExecutionStageExecutorProbe(['unsafe' => true]);
            [$service, $store] = $this->idempotencyHarness();
            $store->nextClaimRecord = $this->record($recordState, $intent, $payload);

            $decision = (new ActionExecutionStage($executor, $service))->process($this->freshState());

            self::assertFalse($decision->continue);
            self::assertSame($expectedCode, $decision->halt?->code);
            self::assertSame([], $decision->halt?->details ?? []);
            self::assertSame(0, $executor->calls);
            self::assertSame(0, $store->completeCalls);
        }
    }

    public function test_claimed_executor_exception_marks_indeterminate_and_rethrows_original(): void
    {
        $expected = new \RuntimeException('side effect may already have happened');
        $executor = new ExecutionStageExecutorProbe(exception: $expected);
        [$service, $store] = $this->idempotencyHarness();

        try {
            (new ActionExecutionStage($executor, $service))->process($this->freshState());
            self::fail('Expected original executor exception.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $executor->calls);
        self::assertSame(1, $store->markIndeterminateCalls);
        self::assertSame(IdempotencyRecordState::Indeterminate, $store->records[self::KEY]->state);
    }

    public function test_indeterminate_transition_failure_does_not_replace_original_executor_exception_or_release_claim(): void
    {
        $expected = new \RuntimeException('side effect uncertain');
        $executor = new ExecutionStageExecutorProbe(exception: $expected);
        [$service, $store] = $this->idempotencyHarness();
        $store->markIndeterminateThrows = true;

        try {
            (new ActionExecutionStage($executor, $service))->process($this->freshState());
            self::fail('Expected original executor exception.');
        } catch (\RuntimeException $actual) {
            self::assertSame($expected, $actual);
        }

        self::assertSame(1, $store->markIndeterminateCalls);
        self::assertSame(IdempotencyRecordState::InProgress, $store->records[self::KEY]->state);
    }

    public function test_unreplayable_successful_output_marks_indeterminate_and_fails_closed(): void
    {
        $executor = new ExecutionStageExecutorProbe(new \stdClass());
        [$service, $store] = $this->idempotencyHarness();

        try {
            (new ActionExecutionStage($executor, $service))->process($this->freshState());
            self::fail('Unreplayable output must not become a successful idempotent result.');
        } catch (UnreplayableIdempotencyOutput) {
            self::assertSame(1, $executor->calls);
        }

        self::assertSame(1, $store->markIndeterminateCalls);
        self::assertSame(IdempotencyRecordState::Indeterminate, $store->records[self::KEY]->state);
    }

    public function test_completion_persistence_failure_never_returns_success_and_keeps_claim_closed(): void
    {
        $executor = new ExecutionStageExecutorProbe(['ok' => true]);
        [$service, $store] = $this->idempotencyHarness();
        $store->completeThrows = true;

        try {
            (new ActionExecutionStage($executor, $service))->process($this->freshState());
            self::fail('Completion persistence failure must propagate.');
        } catch (\RuntimeException $exception) {
            self::assertSame('completion persistence failed', $exception->getMessage());
        }

        self::assertSame(1, $executor->calls);
        self::assertSame(1, $store->completeCalls);
        self::assertSame(IdempotencyRecordState::InProgress, $store->records[self::KEY]->state);
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(interface_exists(ActionExecutor::class), 'ActionExecutor must exist before this test can proceed.');
        self::assertTrue(class_exists(ActionExecutionStage::class), 'ActionExecutionStage must exist before this test can proceed.');
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'prep_list.add_item',
            version: 1,
            title: 'Add preparation item',
            description: 'Adds an item through the shared execution stage.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    private function freshState(): ActionPipelineState
    {
        return (new ActionPipelineState(
            $this->definition(),
            ['name' => 'validated'],
            new InvocationContext('test', 'corr-idem'),
        ))->withIdempotencyPlan(IdempotencyExecutionPlan::fresh(self::KEY, self::INTENT));
    }

    /** @return array{IdempotencyService, ExecutionStageIdempotencyStore} */
    private function idempotencyHarness(): array
    {
        $store = new ExecutionStageIdempotencyStore();
        return [
            new IdempotencyService(
                $store,
                new ExecutionStageIdempotencyClock(1_000),
                new IdempotencyReplayCodec(),
            ),
            $store,
        ];
    }

    private function record(
        IdempotencyRecordState $state,
        string $intentFingerprint,
        ?string $payload,
    ): IdempotencyRecord {
        return new IdempotencyRecord(
            self::KEY,
            $intentFingerprint,
            $state,
            $payload,
            900,
            2_000,
        );
    }
}

final class ExecutionStageExecutorProbe implements ActionExecutor
{
    public int $calls = 0;

    public function __construct(
        private readonly mixed $result = null,
        private readonly ?\Throwable $exception = null,
    ) {}

    public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
    {
        $this->calls++;
        if ($this->exception !== null) {
            throw $this->exception;
        }
        return $this->result;
    }
}

final class ExecutionStageIdempotencyClock implements IdempotencyClock
{
    public function __construct(private readonly int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class ExecutionStageIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    public array $records = [];
    public ?IdempotencyRecord $nextClaimRecord = null;
    public bool $completeThrows = false;
    public bool $markIndeterminateThrows = false;
    public int $claimCalls = 0;
    public int $completeCalls = 0;
    public int $markIndeterminateCalls = 0;

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
        $this->completeCalls++;
        if ($this->completeThrows) {
            throw new \RuntimeException('completion persistence failed');
        }

        $record = $this->records[$keyHash];
        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $intentFingerprint,
            IdempotencyRecordState::Completed,
            $outputPayload,
            $record->createdAt,
            $record->expiresAt,
        );
    }

    public function markIndeterminate(string $keyHash, string $intentFingerprint): void
    {
        $this->markIndeterminateCalls++;
        if ($this->markIndeterminateThrows) {
            throw new \RuntimeException('indeterminate persistence failed');
        }

        $record = $this->records[$keyHash];
        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $intentFingerprint,
            IdempotencyRecordState::Indeterminate,
            null,
            $record->createdAt,
            $record->expiresAt,
        );
    }
}
