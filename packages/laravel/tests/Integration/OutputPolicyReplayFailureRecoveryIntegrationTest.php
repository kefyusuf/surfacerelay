<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

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
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyContext;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputRedactionResult;
use SurfaceRelay\Laravel\OutputPolicy\SensitiveOutputRedactor;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class OutputPolicyReplayFailureRecoveryIntegrationTest extends TestCase
{
    public function test_completed_replay_policy_failure_stays_closed_and_later_policy_only_retry_recovers(): void
    {
        foreach (['withhold', 'throw'] as $failureMode) {
            $definition = new ActionDefinition(
                id: 'orders.private_recovery',
                version: 1,
                title: 'Private recovery',
                description: 'Exercises completed replay disclosure recovery.',
                inputSchema: ['type' => 'object'],
                outputSchema: ['type' => 'object'],
                scope: ActionScope::PageScoped,
                effect: ActionEffect::Read,
                risk: ActionRisk::Low,
                idempotency: IdempotencyPolicy::RequiredKey,
                outputSensitivity: OutputSensitivity::Sensitive,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [],
            );

            $registry = new InMemoryActionRegistry();
            $registry->register($definition);
            $store = new OutputPolicyReplayRecoveryStore();
            $service = new IdempotencyService(
                $store,
                new OutputPolicyReplayRecoveryClock(1_789_000_000),
                new IdempotencyReplayCodec(),
            );
            $executor = new OutputPolicyReplayRecoveryExecutor([
                'public' => 'order-1001',
                'secret' => 'raw-secret-recovery-marker',
            ]);
            $redactor = new OutputPolicyReplayRecoveryRedactor();
            $bus = new ActionBus(
                $registry,
                new OutputPolicyReplayRecoveryAuditor(),
                [
                    new OutputPolicyReplayRecoveryPassThroughStage(ActionPipelineStage::InputValidation),
                    new OutputPolicyReplayRecoveryPassThroughStage(ActionPipelineStage::Authorization),
                    new IdempotencyStage(
                        new IdempotencyKeyValidator(),
                        new IdempotencyKeyHasher(),
                        new IdempotencyIntentHasher(),
                        $service,
                    ),
                    new OutputPolicyReplayRecoveryPassThroughStage(ActionPipelineStage::Confirmation),
                    new ActionExecutionStage($executor, $service),
                    new OutputPolicyStage($redactor),
                ],
            );
            $normalizer = new ActionResultNormalizer();

            $first = $bus->dispatch($this->call($definition, 'corr-first'));
            self::assertTrue($first->completed, $failureMode);
            self::assertSame(1, $executor->calls, $failureMode);
            self::assertSame(IdempotencyRecordState::Completed, $store->onlyRecord()->state, $failureMode);

            $redactor->mode = $failureMode;
            $failedReplay = $bus->dispatch($this->call($definition, 'corr-failed-replay'));
            $failedResult = $normalizer->normalize($failedReplay);

            self::assertFalse($failedReplay->completed, $failureMode);
            self::assertSame(ActionPipelineStage::OutputPolicy, $failedReplay->haltedAt, $failureMode);
            self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $failedReplay->halt?->code, $failureMode);
            self::assertFalse($failedReplay->state->hasOutput, $failureMode);
            self::assertNull($failedReplay->state->output, $failureMode);
            self::assertSame('failed', $failedResult->status->value, $failureMode);
            self::assertSame(1, $executor->calls,
                $failureMode . ': completed replay policy failure must not re-execute application code.');
            self::assertSame(IdempotencyRecordState::Completed, $store->onlyRecord()->state,
                $failureMode . ': output-policy failure must not reopen or downgrade completed idempotency state.');

            $redactor->mode = 'release';
            $recovered = $bus->dispatch($this->call($definition, 'corr-recovered'));
            $recoveredResult = $normalizer->normalize($recovered);

            self::assertTrue($recovered->completed, $failureMode);
            self::assertSame('succeeded', $recoveredResult->status->value, $failureMode);
            self::assertSame('corr-recovered', $recoveredResult->correlationId, $failureMode);
            self::assertSame(['public' => 'order-1001'], $recoveredResult->data, $failureMode);
            self::assertSame(1, $executor->calls,
                $failureMode . ': later recovery must remain policy-only over stored pre-policy output.');
            self::assertSame(IdempotencyRecordState::Completed, $store->onlyRecord()->state, $failureMode);
            self::assertSame(3, $redactor->calls, $failureMode);
            self::assertStringNotContainsString(
                'raw-secret-recovery-marker',
                json_encode($recoveredResult->toArray(), JSON_THROW_ON_ERROR),
                $failureMode,
            );
        }
    }

    private function call(ActionDefinition $definition, string $correlationId): ActionCall
    {
        return new ActionCall(
            $definition->id,
            $definition->version,
            ['orderId' => 1001],
            new InvocationContext(
                surface: 'test',
                correlationId: $correlationId,
                idempotencyKey: 'stable-recovery-key',
            ),
        );
    }
}

final class OutputPolicyReplayRecoveryClock implements IdempotencyClock
{
    public function __construct(private readonly int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class OutputPolicyReplayRecoveryStore implements IdempotencyStore
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing recovery integration claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('Recovery integration intent mismatch.');
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing recovery integration claim.');
        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $record->intentFingerprint,
            IdempotencyRecordState::Indeterminate,
            null,
            $record->createdAt,
            $record->expiresAt,
        );
    }

    public function onlyRecord(): IdempotencyRecord
    {
        if (count($this->records) !== 1) {
            throw new \LogicException('Expected exactly one recovery integration record.');
        }

        return array_values($this->records)[0];
    }
}

final class OutputPolicyReplayRecoveryExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function __construct(private readonly mixed $output) {}

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        ++$this->calls;

        return $this->output;
    }
}

final class OutputPolicyReplayRecoveryRedactor implements SensitiveOutputRedactor
{
    public int $calls = 0;
    public string $mode = 'release';

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        ++$this->calls;

        return match ($this->mode) {
            'release' => OutputRedactionResult::release(['public' => $rawOutput['public']]),
            'withhold' => OutputRedactionResult::withhold(),
            'throw' => throw new \RuntimeException('redactor failure raw-secret-recovery-marker'),
            default => throw new \LogicException('Unknown recovery redactor mode.'),
        };
    }
}

final class OutputPolicyReplayRecoveryPassThroughStage implements ActionPipelineStageHandler
{
    public function __construct(private readonly ActionPipelineStage $pipelineStage) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::continueWith($state);
    }
}

final class OutputPolicyReplayRecoveryAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
