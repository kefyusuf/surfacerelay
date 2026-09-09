<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;
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
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class OutputPolicyReplayIntegrationTest extends TestCase
{
    public function test_completed_idempotency_replay_skips_execution_but_reapplies_current_output_policy(): void
    {
        $definition = new ActionDefinition(
            id: 'orders.secret',
            version: 1,
            title: 'Secret order',
            description: 'Returns sensitive order state.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
        );
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $context = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-replay-policy',
            idempotencyKey: 'replay-policy-key',
        );
        $state = new ActionPipelineState($definition, [], $context);
        $keyHasher = new IdempotencyKeyHasher();
        $intentHasher = new IdempotencyIntentHasher();
        $keyHash = $keyHasher->hash($state, 'replay-policy-key');
        $intentFingerprint = $intentHasher->fingerprint($state);
        $codec = new IdempotencyReplayCodec();
        $rawReplay = ['private' => 'raw-secret', 'orderId' => 41];
        $store = new OutputPolicyReplayStore(new IdempotencyRecord(
            $keyHash,
            $intentFingerprint,
            IdempotencyRecordState::Completed,
            $codec->encode($rawReplay),
            100,
            1000,
        ));
        $service = new IdempotencyService($store, new OutputPolicyReplayClock(200), $codec);
        $redactor = new OutputPolicyReplayRedactor();
        $execution = new OutputPolicyReplayStage(ActionPipelineStage::Execution, true);
        $confirmation = new OutputPolicyReplayStage(ActionPipelineStage::Confirmation, true);

        $bus = new ActionBus(
            $registry,
            new OutputPolicyReplayAuditor(),
            [
                new OutputPolicyReplayStage(ActionPipelineStage::InputValidation),
                new OutputPolicyReplayStage(ActionPipelineStage::Authorization),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    $keyHasher,
                    $intentHasher,
                    $service,
                ),
                $confirmation,
                $execution,
                new OutputPolicyStage($redactor),
            ],
        );

        $outcome = $bus->dispatch(new ActionCall($definition->id, 1, [], $context));

        self::assertTrue($outcome->completed);
        self::assertSame(['orderId' => 41], $outcome->state->output);
        self::assertSame(1, $redactor->calls);
        self::assertSame($rawReplay, $redactor->rawOutput);
        self::assertSame(0, $confirmation->calls, 'Completed replay skips confirmation.');
        self::assertSame(0, $execution->calls, 'Completed replay skips execution.');
        self::assertSame(0, $store->claimCalls, 'Completed preflight replay never claims fresh execution.');
    }
}

final class OutputPolicyReplayClock implements IdempotencyClock
{
    public function __construct(private readonly int $now) {}
    public function now(): int { return $this->now; }
}

final class OutputPolicyReplayStore implements IdempotencyStore
{
    public int $claimCalls = 0;
    public function __construct(private readonly IdempotencyRecord $record) {}
    public function find(string $keyHash): ?IdempotencyRecord { return $keyHash === $this->record->keyHash ? $this->record : null; }
    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult { ++$this->claimCalls; return IdempotencyStoreClaimResult::claimed($fresh); }
    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void { throw new \LogicException('not expected'); }
    public function markIndeterminate(string $keyHash, string $intentFingerprint): void { throw new \LogicException('not expected'); }
}

final class OutputPolicyReplayRedactor implements SensitiveOutputRedactor
{
    public int $calls = 0;
    public mixed $rawOutput = null;
    public function redact(ActionDefinition $definition, mixed $rawOutput, OutputPolicyContext $context): OutputRedactionResult
    {
        ++$this->calls;
        $this->rawOutput = $rawOutput;
        return OutputRedactionResult::release(['orderId' => $rawOutput['orderId']]);
    }
}

final class OutputPolicyReplayStage implements ActionPipelineStageHandler
{
    public int $calls = 0;
    public function __construct(private readonly ActionPipelineStage $stage, private readonly bool $mustNotRun = false) {}
    public function stage(): ActionPipelineStage { return $this->stage; }
    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        ++$this->calls;
        if ($this->mustNotRun) {
            throw new \LogicException($this->stage->value . ' must not run during completed replay');
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class OutputPolicyReplayAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
