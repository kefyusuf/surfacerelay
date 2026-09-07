<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationConfigurationViolation;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Confirmation\VerifiedConfirmation;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ConfirmationStageTest extends TestCase
{
    private const string TOKEN_A = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    private const string TOKEN_B = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';

    public function test_action_bus_delegates_human_confirmation_requirement_to_confirmation_stage(): void
    {
        $definition = $this->definition(
            ActionRisk::Moderate,
            [ContextRequirement::HumanConfirmation],
        );
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $log = [];
        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $handlers[] = new ConfirmationGateProbeHandler($stage, $log);
        }
        $bus = new ActionBus($registry, new ConfirmationGateAuditor(), $handlers);

        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            [],
            new InvocationContext('webmcp', 'corr-kernel'),
        ));

        self::assertTrue($outcome->completed,
            'HumanConfirmation presence must be delegated to the confirmation stage, not rejected by the kernel pre-gate.');
        self::assertContains('confirmation', $log);
    }

    public function test_confirmation_types_exist(): void
    {
        self::assertTrue(class_exists(ConfirmationStage::class), 'ConfirmationStage must exist.');
        self::assertTrue(class_exists(VerifiedConfirmation::class), 'VerifiedConfirmation must exist.');
        self::assertTrue(class_exists(ConfirmationConfigurationViolation::class), 'ConfirmationConfigurationViolation must exist.');
    }

    public function test_low_risk_without_human_confirmation_passes_through_unchanged(): void
    {
        $this->requireTypes();
        [$stage, $store] = $this->stage();
        $state = $this->state($this->definition(ActionRisk::Low));

        $decision = $stage->process($state);

        self::assertTrue($decision->continue);
        self::assertSame($state, $decision->state);
        self::assertSame(0, $store->totalOperations());
    }

    public function test_explicit_human_confirmation_requires_challenge_even_when_risk_is_not_consequential(): void
    {
        $this->requireTypes();
        [$stage, $store] = $this->stage();
        $state = $this->state($this->definition(
            ActionRisk::Moderate,
            [ContextRequirement::HumanConfirmation],
        ));

        $decision = $stage->process($state);

        self::assertFalse($decision->continue);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $decision->halt?->code);
        self::assertSame(self::TOKEN_A, $decision->halt?->confirmation?->challengeId);
        self::assertSame('Approve refund', $decision->halt?->confirmation?->summary);
        self::assertSame(1, $store->createCalls);
    }

    public function test_consequential_risk_requires_challenge_even_if_definition_omits_context_requirement(): void
    {
        $this->requireTypes();
        [$stage] = $this->stage();
        $decision = $stage->process($this->state($this->definition(ActionRisk::Consequential)));

        self::assertFalse($decision->continue);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $decision->halt?->code);
        self::assertNotNull($decision->halt?->confirmation);
    }

    public function test_pre_materialized_human_confirmation_is_a_configuration_violation(): void
    {
        $this->requireTypes();
        [$stage] = $this->stage();
        $context = new InvocationContext('webmcp', 'corr-preloaded', [
            new TrustedContextEntry(
                ContextRequirement::HumanConfirmation,
                'caller-or-miswired-authority',
                new ContextProvenance('bad.provider'),
            ),
        ]);

        $this->expectException(ConfirmationConfigurationViolation::class);
        $stage->process($this->state(
            $this->definition(ActionRisk::Consequential),
            context: $context,
        ));
    }

    public function test_missing_or_invalid_receipt_produces_fresh_challenge_and_never_materializes_authority(): void
    {
        $this->requireTypes();
        [$stage, $store] = $this->stage(tokens: [self::TOKEN_A, self::TOKEN_B]);
        $definition = $this->definition(ActionRisk::Consequential);

        $missing = $stage->process($this->state($definition));
        self::assertFalse($missing->continue);
        self::assertSame(self::TOKEN_A, $missing->halt?->confirmation?->challengeId);
        self::assertFalse($missing->state->context->has(ContextRequirement::HumanConfirmation));

        $invalid = $stage->process($this->state($definition, receipt: self::TOKEN_A));
        self::assertFalse($invalid->continue,
            'A pending challenge token supplied as a receipt candidate must not grant authority.');
        self::assertSame(self::TOKEN_B, $invalid->halt?->confirmation?->challengeId);
        self::assertFalse($invalid->state->context->has(ContextRequirement::HumanConfirmation));
        self::assertSame(1, $store->consumeCalls);
    }

    public function test_exact_approved_receipt_materializes_verified_confirmation_and_continues(): void
    {
        $this->requireTypes();
        [$stage, $store, $service, $hasher] = $this->stage();
        $definition = $this->definition(ActionRisk::Consequential);
        $base = $this->state($definition, input: ['amount' => 100]);
        $scope = $hasher->fingerprint($base);
        $challenge = $service->issueChallenge($scope, $definition->title);
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertSame(self::TOKEN_A, $receipt);

        $decision = $stage->process($this->state(
            $definition,
            input: ['amount' => 100],
            receipt: $receipt,
        ));

        self::assertTrue($decision->continue);
        $entry = $decision->state->context->require(ContextRequirement::HumanConfirmation);
        self::assertInstanceOf(VerifiedConfirmation::class, $entry->value);
        self::assertSame('surfacerelay.confirmation', $entry->provenance->provider);
        self::assertNull($entry->provenance->reference);
        self::assertSame('verified', $entry->confirmationScopeKey);
        self::assertSame([], get_object_vars($entry->value), 'Verified marker must carry no receipt/hash/scope properties.');
        self::assertSame(1, $store->consumeCalls);
        self::assertSame(0, $store->recordCount(), 'Successful verification must consume the approved record before continuation.');
    }

    public function test_bus_confirmation_halt_short_circuits_idempotency_and_execution(): void
    {
        $this->requireTypes();
        [$confirmationStage] = $this->stage();
        $definition = $this->definition(ActionRisk::Consequential);
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $log = [];
        $handlers = [
            new ConfirmationGateProbeHandler(ActionPipelineStage::InputValidation, $log),
            new ConfirmationGateProbeHandler(ActionPipelineStage::Authorization, $log),
            $confirmationStage,
            new ConfirmationGateProbeHandler(ActionPipelineStage::Idempotency, $log),
            new ConfirmationGateProbeHandler(ActionPipelineStage::Execution, $log),
            new ConfirmationGateProbeHandler(ActionPipelineStage::OutputPolicy, $log),
        ];
        $bus = new ActionBus($registry, new ConfirmationGateAuditor(), $handlers);

        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            [],
            new InvocationContext('webmcp', 'corr-halt'),
            bindingId: 'binding-1',
        ));

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Confirmation, $outcome->haltedAt);
        self::assertSame(['input_validation', 'authorization'], $log,
            'No idempotency/execution/output stage may run after confirmation_required.');
    }

    /** @return array{ConfirmationStage, StageConfirmationStore, ConfirmationService, ConfirmationScopeHasher} */
    private function stage(array $tokens = [self::TOKEN_A]): array
    {
        $store = new StageConfirmationStore();
        $clock = new StageConfirmationClock(1_789_000_000);
        $service = new ConfirmationService($store, $clock, new StageTokenGenerator($tokens));
        $hasher = new ConfirmationScopeHasher();
        return [new ConfirmationStage($service, $hasher), $store, $service, $hasher];
    }

    /** @param list<ContextRequirement> $requirements */
    private function definition(ActionRisk $risk, array $requirements = []): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Approve refund',
            description: 'Approves the exact refund intent.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: $risk,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    /** @param array<string, mixed> $input */
    private function state(
        ActionDefinition $definition,
        array $input = [],
        ?InvocationContext $context = null,
        ?string $receipt = null,
    ): ActionPipelineState {
        return new ActionPipelineState(
            definition: $definition,
            input: $input,
            context: $context ?? new InvocationContext('webmcp', 'corr-stage'),
            bindingId: 'binding-1',
            confirmationReceipt: $receipt,
        );
    }

    private function requireTypes(): void
    {
        $this->test_confirmation_types_exist();
    }
}

final class StageConfirmationClock implements ConfirmationClock
{
    public function __construct(private readonly int $timestamp) {}
    public function now(): int { return $this->timestamp; }
}

final class StageTokenGenerator implements ConfirmationTokenGenerator
{
    private int $index = 0;
    /** @param list<string> $tokens */
    public function __construct(private readonly array $tokens) {}
    public function generate(): string
    {
        return $this->tokens[$this->index++] ?? throw new \RuntimeException('Token queue exhausted.');
    }
}

final class StageConfirmationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];
    public int $createCalls = 0;
    public int $approveCalls = 0;
    public int $consumeCalls = 0;

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        $this->createCalls++;
        if (isset($this->records[$tokenHash])) {
            return false;
        }
        $this->records[$tokenHash] = $record;
        return true;
    }

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
    {
        $this->approveCalls++;
        $record = $this->records[$tokenHash] ?? null;
        if ($record === null || $record->state !== ConfirmationRecordState::Pending || $now >= $record->challengeExpiresAt) {
            return false;
        }
        $this->records[$tokenHash] = new ConfirmationRecord(
            ConfirmationRecordState::Approved,
            $record->scopeFingerprint,
            $record->summary,
            $record->issuedAt,
            $record->challengeExpiresAt,
            $receiptExpiresAt,
        );
        return true;
    }

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool
    {
        $this->consumeCalls++;
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

    public function totalOperations(): int
    {
        return $this->createCalls + $this->approveCalls + $this->consumeCalls;
    }

    public function recordCount(): int
    {
        return count($this->records);
    }
}

final class ConfirmationGateProbeHandler implements ActionPipelineStageHandler
{
    /** @var list<string> */
    private array $log;

    /** @param list<string> $log */
    public function __construct(private readonly ActionPipelineStage $stage, array &$log)
    {
        $this->log = &$log;
    }

    public function stage(): ActionPipelineStage { return $this->stage; }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->log[] = $this->stage->value;
        if ($this->stage === ActionPipelineStage::Execution) {
            return ActionPipelineDecision::continueWith($state->withOutput('executed'));
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class ConfirmationGateAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void {}
}
