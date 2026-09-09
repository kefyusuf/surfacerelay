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
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class StructuredAuditIdempotencyReplayIntegrationTest extends TestCase
{
    public function test_consequential_completed_replay_creates_new_audit_event_without_second_execution_or_confirmation(): void
    {
        $definition = StructuredAuditReplayHarness::definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $idempotencyStore = new StructuredAuditReplayStore();
        $idempotency = new IdempotencyService(
            $idempotencyStore,
            new StructuredAuditReplayClock(1_789_000_000),
            new IdempotencyReplayCodec(),
        );
        $confirmationStore = new StructuredAuditReplayConfirmationStore();
        $confirmationService = new ConfirmationService(
            $confirmationStore,
            new StructuredAuditReplayConfirmationClock(1_789_000_000),
            new StructuredAuditReplayTokenGenerator(),
        );
        $executor = new StructuredAuditReplayExecutor([
            'public' => 'order-1001',
            'secret' => 'SECRET_REPLAY_RAW_OUTPUT',
        ]);
        $redactor = new StructuredAuditReplayRedactor();
        $auditStore = new StructuredAuditReplayAuditStore();

        $bus = new ActionBus(
            $registry,
            new StructuredActionPipelineAuditor(
                new AuditEventFactory(new StructuredAuditReplayAuditClock()),
                $auditStore,
            ),
            [
                new StructuredAuditReplayPassThroughStage(ActionPipelineStage::InputValidation),
                new StructuredAuditReplayPassThroughStage(ActionPipelineStage::Authorization),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $idempotency,
                ),
                new ConfirmationStage($confirmationService, new ConfirmationScopeHasher()),
                new ActionExecutionStage($executor, $idempotency),
                new OutputPolicyStage($redactor),
            ],
        );

        $pending = $bus->dispatch(StructuredAuditReplayHarness::call(
            $definition,
            'corr-challenge',
        ));
        $challenge = $pending->halt?->confirmation;

        self::assertFalse($pending->completed);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $pending->halt?->code);
        self::assertNotNull($challenge);
        self::assertSame(0, $executor->calls);
        self::assertSame(0, $redactor->calls);
        self::assertCount(1, $auditStore->events);
        self::assertSame(AuditOutcomeKind::Halted, $auditStore->events[0]->outcomeKind);
        self::assertFalse($auditStore->events[0]->humanConfirmationPresent);

        $receipt = $confirmationService->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $first = $bus->dispatch(StructuredAuditReplayHarness::call(
            $definition,
            'corr-first',
            $receipt,
        ));

        self::assertTrue($first->completed);
        self::assertSame(1, $executor->calls);
        self::assertSame(1, $redactor->calls);
        self::assertSame([
            'public' => 'SECRET_REPLAY_RELEASED_OUTPUT',
            'policyRevision' => 1,
        ], $first->state->output);
        self::assertCount(2, $auditStore->events);
        self::assertSame(AuditOutcomeKind::Completed, $auditStore->events[1]->outcomeKind);
        self::assertSame('corr-first', $auditStore->events[1]->correlationId);
        self::assertTrue($auditStore->events[1]->humanConfirmationPresent);

        $replay = $bus->dispatch(StructuredAuditReplayHarness::call(
            $definition,
            'corr-replay',
        ));

        self::assertTrue($replay->completed);
        self::assertSame(1, $executor->calls,
            'Exact completed replay must never execute application code twice.');
        self::assertSame(2, $redactor->calls,
            'Replay must rerun current output policy over stored pre-policy output.');
        self::assertSame([
            'public' => 'SECRET_REPLAY_RELEASED_OUTPUT',
            'policyRevision' => 2,
        ], $replay->state->output);
        self::assertSame([
            'public' => 'order-1001',
            'secret' => 'SECRET_REPLAY_RAW_OUTPUT',
        ], $redactor->rawOutputs[1]);

        self::assertCount(3, $auditStore->events,
            'The confirmation challenge, first completed invocation and exact replay each finalize separately.');
        $firstEvent = $auditStore->events[1];
        $replayEvent = $auditStore->events[2];
        self::assertNotSame($firstEvent->eventId, $replayEvent->eventId);
        self::assertSame('corr-first', $firstEvent->correlationId);
        self::assertSame('corr-replay', $replayEvent->correlationId);
        self::assertTrue($firstEvent->humanConfirmationPresent);
        self::assertFalse($replayEvent->humanConfirmationPresent,
            'Completed replay skips confirmation and must not manufacture prior human-confirmation authority.');

        foreach ([$firstEvent, $replayEvent] as $event) {
            $serialized = StructuredAuditReplayHarness::serializeEvent($event);
            foreach ([
                'SECRET_REPLAY_RAW_OUTPUT',
                'SECRET_REPLAY_RELEASED_OUTPUT',
                'stable-audit-replay-key',
                $receipt,
                $challenge->challengeId,
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $serialized);
            }
        }
    }
}

final class StructuredAuditReplayHarness
{
    public static function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'audit.replay',
            version: 1,
            title: 'Audit replay',
            description: 'Exercises structured audit on a consequential exact idempotency replay.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: ['type' => 'object'],
        );
    }

    public static function call(
        ActionDefinition $definition,
        string $correlationId,
        ?string $confirmationReceipt = null,
    ): ActionCall {
        return new ActionCall(
            $definition->id,
            $definition->version,
            ['orderId' => 1001],
            new InvocationContext(
                surface: 'webmcp',
                correlationId: $correlationId,
                idempotencyKey: 'stable-audit-replay-key',
            ),
            bindingId: 'binding-audit-replay',
            confirmationReceipt: $confirmationReceipt,
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

final class StructuredAuditReplayClock implements IdempotencyClock
{
    public function __construct(private int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class StructuredAuditReplayStore implements IdempotencyStore
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing structured-audit replay claim.');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('Structured-audit replay intent mismatch.');
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('Missing structured-audit replay claim.');
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

final class StructuredAuditReplayExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function __construct(private readonly mixed $output) {}

    public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
    {
        ++$this->calls;
        return $this->output;
    }
}

final class StructuredAuditReplayRedactor implements SensitiveOutputRedactor
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
            'public' => 'SECRET_REPLAY_RELEASED_OUTPUT',
            'policyRevision' => $this->calls,
        ]);
    }
}

final readonly class StructuredAuditReplayPassThroughStage implements ActionPipelineStageHandler
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

final class StructuredAuditReplayAuditStore implements AuditEventStore
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}

final class StructuredAuditReplayAuditClock implements AuditClock
{
    private int $microseconds = 0;

    public function now(): DateTimeImmutable
    {
        $suffix = str_pad((string) $this->microseconds++, 6, '0', STR_PAD_LEFT);
        return new DateTimeImmutable(
            '2026-09-09 17:45:00.' . $suffix,
            new DateTimeZone('UTC'),
        );
    }
}

final class StructuredAuditReplayConfirmationClock implements ConfirmationClock
{
    public function __construct(private int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class StructuredAuditReplayTokenGenerator implements ConfirmationTokenGenerator
{
    private int $index = 0;

    public function generate(): string
    {
        $tokens = [
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB',
            'CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC',
        ];

        return $tokens[$this->index++] ?? throw new \RuntimeException('Structured-audit replay token queue exhausted.');
    }
}

final class StructuredAuditReplayConfirmationStore implements ConfirmationStore
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
