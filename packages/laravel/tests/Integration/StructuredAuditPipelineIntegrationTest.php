<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEvent;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\AuditEventStore;
use SurfaceRelay\Laravel\Audit\AuditOutcomeKind;
use SurfaceRelay\Laravel\Audit\StructuredActionPipelineAuditor;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyContext;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputRedactionResult;
use SurfaceRelay\Laravel\OutputPolicy\SensitiveOutputRedactor;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class StructuredAuditPipelineIntegrationTest extends TestCase
{
    public function test_successful_and_pre_stage_halted_dispatches_each_emit_one_final_event(): void
    {
        $store = new StructuredAuditMemoryStore();
        $executor = new StructuredAuditCountingExecutor(['ok' => true]);
        $definition = StructuredAuditIntegrationHarness::definition();
        $bus = StructuredAuditIntegrationHarness::bus($definition, $executor, $store);

        $completed = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['secret' => 'SECRET_CALL_INPUT'],
            new InvocationContext('webmcp', 'corr-completed'),
        ));

        self::assertTrue($completed->completed);
        self::assertSame(1, $executor->calls);
        self::assertCount(1, $store->events);
        self::assertSame(AuditOutcomeKind::Completed, $store->events[0]->outcomeKind);
        self::assertSame('corr-completed', $store->events[0]->correlationId);

        $requiredTenant = StructuredAuditIntegrationHarness::definition(
            id: 'audit.requires_tenant',
            requirements: [ContextRequirement::Tenant],
        );
        $haltStore = new StructuredAuditMemoryStore();
        $haltExecutor = new StructuredAuditCountingExecutor(['never' => true]);
        $haltBus = StructuredAuditIntegrationHarness::bus($requiredTenant, $haltExecutor, $haltStore);
        $halted = $haltBus->dispatch(new ActionCall(
            $requiredTenant->id,
            $requiredTenant->version,
            [],
            new InvocationContext('webmcp', 'corr-missing-context'),
        ));

        self::assertFalse($halted->completed);
        self::assertNull($halted->haltedAt);
        self::assertSame(CoreActionErrorCode::REQUIRED_CONTEXT_MISSING, $halted->halt?->code);
        self::assertSame(0, $haltExecutor->calls);
        self::assertCount(1, $haltStore->events);
        self::assertSame(AuditOutcomeKind::Halted, $haltStore->events[0]->outcomeKind);
        self::assertNull($haltStore->events[0]->haltedAt);
        self::assertSame(CoreActionErrorCode::REQUIRED_CONTEXT_MISSING, $haltStore->events[0]->haltCode);
    }

    public function test_real_consequential_confirmation_records_presence_only_after_receipt_consumption(): void
    {
        $store = new StructuredAuditMemoryStore();
        $executor = new StructuredAuditCountingExecutor(['approved' => true]);
        $definition = StructuredAuditIntegrationHarness::definition(
            id: 'audit.confirmed',
            risk: ActionRisk::Consequential,
        );
        $confirmationStore = new StructuredAuditConfirmationStore();
        $confirmationClock = new StructuredAuditConfirmationClock(strtotime('2026-09-09T17:00:00Z'));
        $tokens = new StructuredAuditTokenGenerator();
        $service = new ConfirmationService($confirmationStore, $confirmationClock, $tokens);
        $bus = StructuredAuditIntegrationHarness::bus(
            $definition,
            $executor,
            $store,
            confirmation: new ConfirmationStage($service, new ConfirmationScopeHasher()),
        );
        $context = new InvocationContext(
            'webmcp',
            'corr-confirm-challenge',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::Tenant,
                    'SECRET_TENANT_ID',
                    new ContextProvenance('tenant.provider', 'SECRET_TENANT_REFERENCE'),
                    confirmationScopeKey: 'SECRET_TENANT_SCOPE',
                ),
            ],
        );

        $pending = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['amount' => 100],
            $context,
            bindingId: 'binding-confirmed',
        ));
        $challenge = $pending->halt?->confirmation;

        self::assertNotNull($challenge);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $pending->halt?->code);
        self::assertCount(1, $store->events);
        self::assertFalse($store->events[0]->humanConfirmationPresent);
        self::assertSame(0, $executor->calls);

        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);
        $confirmed = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['amount' => 100],
            new InvocationContext(
                'webmcp',
                'corr-confirmed',
                trustedContext: $context->allTrusted(),
            ),
            bindingId: 'binding-confirmed',
            confirmationReceipt: $receipt,
        ));

        self::assertTrue($confirmed->completed);
        self::assertSame(1, $executor->calls);
        self::assertCount(2, $store->events);
        $event = $store->events[1];
        self::assertTrue($event->humanConfirmationPresent);
        $manifest = array_map(
            static fn ($entry): array => [$entry->requirement, $entry->provider],
            $event->trustedContextManifest,
        );
        self::assertContains(
            [ContextRequirement::HumanConfirmation, 'surfacerelay.confirmation'],
            $manifest,
        );

        $serialized = StructuredAuditIntegrationHarness::serializeEvent($event);
        foreach ([
            $receipt,
            $challenge->challengeId,
            'SECRET_TENANT_ID',
            'SECRET_TENANT_REFERENCE',
            'SECRET_TENANT_SCOPE',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $serialized);
        }
    }

    public function test_real_output_policy_failure_and_sensitive_release_never_put_output_in_audit_event(): void
    {
        $rawMarker = 'SECRET_RAW_OUTPUT';
        $releasedMarker = 'SECRET_RELEASED_OUTPUT';
        $definition = StructuredAuditIntegrationHarness::definition(
            id: 'audit.sensitive',
            sensitivity: OutputSensitivity::Sensitive,
        );

        $withholdStore = new StructuredAuditMemoryStore();
        $withholdExecutor = new StructuredAuditCountingExecutor(['private' => $rawMarker]);
        $withholdBus = StructuredAuditIntegrationHarness::bus(
            $definition,
            $withholdExecutor,
            $withholdStore,
            redactor: new StructuredAuditRedactor(OutputRedactionResult::withhold()),
        );
        $withheld = $withholdBus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            [],
            new InvocationContext('webmcp', 'corr-withheld'),
        ));

        self::assertFalse($withheld->completed);
        self::assertSame(ActionPipelineStage::OutputPolicy, $withheld->haltedAt);
        self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $withheld->halt?->code);
        self::assertCount(1, $withholdStore->events);
        self::assertSame(ActionPipelineStage::OutputPolicy, $withholdStore->events[0]->haltedAt);
        self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $withholdStore->events[0]->haltCode);
        self::assertStringNotContainsString(
            $rawMarker,
            StructuredAuditIntegrationHarness::serializeEvent($withholdStore->events[0]),
        );

        $releaseStore = new StructuredAuditMemoryStore();
        $releaseExecutor = new StructuredAuditCountingExecutor(['private' => $rawMarker]);
        $releaseBus = StructuredAuditIntegrationHarness::bus(
            $definition,
            $releaseExecutor,
            $releaseStore,
            redactor: new StructuredAuditRedactor(
                OutputRedactionResult::release(['safe' => $releasedMarker]),
            ),
        );
        $released = $releaseBus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            [],
            new InvocationContext('webmcp', 'corr-released'),
        ));

        self::assertTrue($released->completed);
        self::assertSame(['safe' => $releasedMarker], $released->state->output);
        self::assertCount(1, $releaseStore->events);
        $releasedAudit = StructuredAuditIntegrationHarness::serializeEvent($releaseStore->events[0]);
        self::assertStringNotContainsString($rawMarker, $releasedAudit);
        self::assertStringNotContainsString($releasedMarker, $releasedAudit);
    }

    public function test_audit_store_failure_escapes_after_execution_without_rollback_or_retry(): void
    {
        $failure = new \RuntimeException('audit-store-test-failure');
        $store = new StructuredAuditMemoryStore($failure);
        $executor = new StructuredAuditCountingExecutor(['sideEffect' => true]);
        $definition = StructuredAuditIntegrationHarness::definition(id: 'audit.store_failure');
        $bus = StructuredAuditIntegrationHarness::bus($definition, $executor, $store);

        try {
            $bus->dispatch(new ActionCall(
                $definition->id,
                $definition->version,
                [],
                new InvocationContext('webmcp', 'corr-store-failure'),
            ));
            self::fail('Audit store failure must escape finalization.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }

        self::assertSame(1, $executor->calls,
            'Audit failure occurs after application execution and must not retry or imply rollback.');
        self::assertSame(1, $store->appendCalls);
    }
}

final class StructuredAuditIntegrationHarness
{
    /** @param list<ContextRequirement> $requirements */
    public static function definition(
        string $id = 'audit.success',
        ActionRisk $risk = ActionRisk::Low,
        OutputSensitivity $sensitivity = OutputSensitivity::Normal,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: 1,
            title: 'Structured audit integration',
            description: 'Exercises the final structured audit boundary.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: $risk,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: $sensitivity,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
            outputSchema: ['type' => 'object'],
        );
    }

    public static function bus(
        ActionDefinition $definition,
        ActionExecutor $executor,
        AuditEventStore $store,
        ?ActionPipelineStageHandler $confirmation = null,
        ?SensitiveOutputRedactor $redactor = null,
    ): ActionBus {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        return new ActionBus(
            $registry,
            new StructuredActionPipelineAuditor(
                new AuditEventFactory(new StructuredAuditFixedClock()),
                $store,
            ),
            [
                new StructuredAuditPassThroughStage(ActionPipelineStage::InputValidation),
                new StructuredAuditPassThroughStage(ActionPipelineStage::Authorization),
                new StructuredAuditPassThroughStage(ActionPipelineStage::Idempotency),
                $confirmation ?? new StructuredAuditPassThroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor),
                $redactor === null
                    ? new StructuredAuditPassThroughStage(ActionPipelineStage::OutputPolicy)
                    : new OutputPolicyStage($redactor),
            ],
        );
    }

    public static function serializeEvent(AuditEvent $event): string
    {
        return json_encode([
            'eventId' => $event->eventId,
            'recordedAt' => $event->recordedAt->format('Y-m-d H:i:s.u'),
            'correlationId' => $event->correlationId,
            'surface' => $event->surface,
            'actionId' => $event->actionId,
            'actionVersion' => $event->actionVersion,
            'actionScope' => $event->actionScope->value,
            'actionEffect' => $event->actionEffect->value,
            'actionRisk' => $event->actionRisk->value,
            'idempotencyPolicy' => $event->idempotencyPolicy->value,
            'outputSensitivity' => $event->outputSensitivity->value,
            'outputContentTrust' => $event->outputContentTrust->value,
            'outcomeKind' => $event->outcomeKind->value,
            'haltedAt' => $event->haltedAt?->value,
            'haltCode' => $event->haltCode,
            'humanConfirmationPresent' => $event->humanConfirmationPresent,
            'trustedContextManifest' => array_map(
                static fn ($entry): array => [
                    'requirement' => $entry->requirement->value,
                    'provider' => $entry->provider,
                ],
                $event->trustedContextManifest,
            ),
        ], JSON_THROW_ON_ERROR);
    }
}

final class StructuredAuditMemoryStore implements AuditEventStore
{
    /** @var list<AuditEvent> */
    public array $events = [];
    public int $appendCalls = 0;

    public function __construct(private readonly ?\RuntimeException $failure = null) {}

    public function append(AuditEvent $event): void
    {
        ++$this->appendCalls;
        if ($this->failure !== null) {
            throw $this->failure;
        }
        $this->events[] = $event;
    }
}

final class StructuredAuditFixedClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-09-09 17:30:00.123456', new DateTimeZone('UTC'));
    }
}

final readonly class StructuredAuditPassThroughStage implements ActionPipelineStageHandler
{
    public function __construct(private ActionPipelineStage $pipelineStage) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::continueWith($state);
    }
}

final class StructuredAuditCountingExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function __construct(private readonly mixed $output) {}

    public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
    {
        ++$this->calls;
        return $this->output;
    }
}

final class StructuredAuditRedactor implements SensitiveOutputRedactor
{
    public function __construct(private readonly OutputRedactionResult $result) {}

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        return $this->result;
    }
}

final class StructuredAuditConfirmationClock implements ConfirmationClock
{
    public function __construct(private int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class StructuredAuditTokenGenerator implements ConfirmationTokenGenerator
{
    private int $index = 0;

    public function generate(): string
    {
        $tokens = [
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB',
            'CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC',
        ];

        return $tokens[$this->index++] ?? throw new \RuntimeException('Structured audit token queue exhausted.');
    }
}

final class StructuredAuditConfirmationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        if (isset($this->records[$tokenHash])) {
            return false;
        }
        $this->records[$tokenHash] = $record;
        return true;
    }

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool
    {
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
}
