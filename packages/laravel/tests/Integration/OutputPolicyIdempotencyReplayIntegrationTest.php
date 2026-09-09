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

final class OutputPolicyIdempotencyReplayIntegrationTest extends TestCase
{
    public function test_completed_replay_skips_execution_but_reruns_current_sensitive_output_policy(): void
    {
        $definition = new ActionDefinition(
            id: 'orders.private_replay',
            version: 1,
            title: 'Private replay',
            description: 'Returns replayable sensitive application data.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: ['type' => 'object'],
        );

        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $clock = new OutputPolicyReplayClock(1_789_000_000);
        $store = new OutputPolicyReplayStore();
        $idempotency = new IdempotencyService(
            $store,
            $clock,
            new IdempotencyReplayCodec(),
        );
        $executor = new OutputPolicyReplayExecutor([
            'public' => 'order-1001',
            'secret' => 'raw-secret-replay-marker',
        ]);
        $redactor = new OutputPolicyReplayRedactor();
        $auditor = new OutputPolicyReplayAuditor();
        $bus = new ActionBus(
            $registry,
            $auditor,
            [
                new OutputPolicyReplayPassThroughStage(ActionPipelineStage::InputValidation),
                new OutputPolicyReplayPassThroughStage(ActionPipelineStage::Authorization),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $idempotency,
                ),
                new OutputPolicyReplayPassThroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor, $idempotency),
                new OutputPolicyStage($redactor),
            ],
        );

        $first = $bus->dispatch($this->call($definition, 'corr-first'));
        $firstResult = (new ActionResultNormalizer())->normalize($first);

        self::assertTrue($first->completed);
        self::assertSame(1, $executor->calls);
        self::assertSame(1, $redactor->calls);
        self::assertSame([
            'public' => 'order-1001',
            'policyRevision' => 1,
        ], $firstResult->data);

        $second = $bus->dispatch($this->call($definition, 'corr-replay'));
        $secondResult = (new ActionResultNormalizer())->normalize($second);

        self::assertTrue($second->completed);
        self::assertSame('corr-replay', $secondResult->correlationId);
        self::assertSame(1, $executor->calls,
            'Completed replay must not execute application code again.');
        self::assertSame(2, $redactor->calls,
            'Completed replay must rerun the current output policy over stored pre-policy output.');
        self::assertSame([
            'public' => 'order-1001',
            'policyRevision' => 2,
        ], $secondResult->data,
            'Replay must use the current redaction decision rather than replaying the prior disclosed payload.');
        self::assertSame([
            'public' => 'order-1001',
            'secret' => 'raw-secret-replay-marker',
        ], $redactor->rawOutputs[1]);
        self::assertStringNotContainsString(
            'raw-secret-replay-marker',
            json_encode($secondResult->toArray(), JSON_THROW_ON_ERROR),
        );
        self::assertSame(2, $auditor->calls);
        self::assertSame($second, $auditor->outcomes[1]);
        self::assertSame($secondResult->data, $auditor->outcomes[1]->state->output,
            'Audit sees the replay only after current output policy sanitization.');
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
                idempotencyKey: 'stable-replay-key',
            ),
        );
    }
}

final class OutputPolicyReplayClock implements IdempotencyClock
{
    public function __construct(private int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class OutputPolicyReplayStore implements IdempotencyStore
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing replay integration claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('Replay integration intent mismatch.');
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing replay integration claim.');
        $this->records[$keyHash] = new IdempotencyRecord(
            $record->keyHash,
            $record->intentFingerprint,
            IdempotencyRecordState::Indeterminate,
            null,
            $record->createdAt,
            $record->expiresAt,
        );
    }
}

final class OutputPolicyReplayExecutor implements ActionExecutor
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

final class OutputPolicyReplayRedactor implements SensitiveOutputRedactor
{
    public int $calls = 0;

    /** @var list<mixed> */
    public array $rawOutputs = [];

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        ++$this->calls;
        $this->rawOutputs[] = $rawOutput;

        return OutputRedactionResult::release([
            'public' => $rawOutput['public'],
            'policyRevision' => $this->calls,
        ]);
    }
}

final class OutputPolicyReplayPassThroughStage implements ActionPipelineStageHandler
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

final class OutputPolicyReplayAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;

    /** @var list<ActionPipelineOutcome> */
    public array $outcomes = [];

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        ++$this->calls;
        $this->outcomes[] = $outcome;
    }
}
