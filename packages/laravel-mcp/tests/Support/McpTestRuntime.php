<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Support;

use DateTimeImmutable;
use DateTimeZone;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEvent;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\AuditEventStore;
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
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
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
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\LaravelMcp\Invocation\McpActionGateway;

final class McpTestRuntime
{
    /**
     * @param list<ContextRequirement> $requirements
     */
    public static function definition(
        string $id = 'orders.find',
        int $version = 1,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Test MCP action',
            description: 'Exercises the MCP invocation bridge.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    /**
     * Task 6 definition helper for real trusted-pipeline evidence.
     *
     * @param list<ContextRequirement> $requirements
     */
    public static function pipelineDefinition(
        string $id,
        int $version,
        array $requirements = [],
        OutputSensitivity $sensitivity = OutputSensitivity::Normal,
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Task 6 MCP action',
            description: 'Exercises trusted pipeline convergence.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: $sensitivity,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    /**
     * Builds one exact real ActionBus pipeline for Task 6 evidence.
     *
     * Real production stages are used where behavior is under examination:
     * OutputPolicyStage and StructuredActionPipelineAuditor. Other stages are
     * deterministic pass-through test handlers except the explicit
     * authorization-denial test behavior and capturing execution stage.
     *
     * @return array{
     *   gateway: McpActionGateway,
     *   executor: CapturingMcpExecutionStage,
     *   auditStore: InMemoryMcpAuditEventStore
     * }
     */
    public static function pipelineGateway(
        ActionDefinition $definition,
        mixed $actor = 'trusted-user',
        mixed $tenant = 'trusted-tenant',
        bool $authorizationDenied = false,
        mixed $executionOutput = ['executed' => true],
        mixed $releaseSensitiveOutput = null,
    ): array {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $executor = new CapturingMcpExecutionStage($executionOutput);
        $auditStore = new InMemoryMcpAuditEventStore();
        $auditor = new StructuredActionPipelineAuditor(
            new AuditEventFactory(new FixedMcpAuditClock()),
            $auditStore,
        );

        $authorization = new McpTestStageHandler(
            ActionPipelineStage::Authorization,
            $authorizationDenied
                ? static fn (ActionPipelineState $state): ActionPipelineDecision
                    => ActionPipelineDecision::halt(
                        new ActionPipelineHalt(CoreActionErrorCode::AUTHORIZATION_DENIED),
                        $state,
                    )
                : null,
        );

        $redactor = $releaseSensitiveOutput === null
            ? null
            : new ReleasingMcpSensitiveOutputRedactor($releaseSensitiveOutput);

        $bus = new ActionBus(
            $registry,
            $auditor,
            [
                new McpTestStageHandler(ActionPipelineStage::InputValidation),
                $authorization,
                new McpTestStageHandler(ActionPipelineStage::Idempotency),
                new McpTestStageHandler(ActionPipelineStage::Confirmation),
                $executor,
                new OutputPolicyStage($redactor),
            ],
        );

        return [
            'gateway' => new McpActionGateway(
                $bus,
                new ActionResultNormalizer(),
                self::composer($actor, $tenant),
            ),
            'executor' => $executor,
            'auditStore' => $auditStore,
        ];
    }

    public static function serializeAuditEvent(AuditEvent $event): string
    {
        return json_encode(
            [
                'eventId' => $event->eventId,
                'recordedAt' => $event->recordedAt->format(DATE_ATOM),
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
                    static fn ($entry): array => $entry->requirement !== null
                        ? [
                            'requirement' => $entry->requirement->value,
                            'provider' => $entry->provider,
                        ]
                        : [
                            'extension' => $entry->extension,
                            'provider' => $entry->provider,
                        ],
                    $event->trustedContextManifest,
                ),
            ],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );
    }

    /**
     * @return array{
     *   definition: ActionDefinition,
     *   gateway: McpActionGateway,
     *   confirmationService: ConfirmationService,
     *   executor: CountingMcpActionExecutor
     * }
     */
    public static function confirmationAuthorityRuntime(): array
    {
        $definition = new ActionDefinition(
            id: 'orders.cancel',
            version: 1,
            title: 'Cancel order',
            description: 'Exercises MCP confirmation authority.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );

        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $clock = new FixedMcpAuthorityClock();
        $confirmationStore = new InMemoryMcpConfirmationStore();
        $confirmationService = new ConfirmationService(
            $confirmationStore,
            $clock,
            new SequentialMcpConfirmationTokenGenerator(),
        );
        $executor = new CountingMcpActionExecutor();

        $bus = new ActionBus(
            $registry,
            new CapturingActionPipelineAuditor(),
            [
                new McpTestStageHandler(ActionPipelineStage::InputValidation),
                new McpTestStageHandler(ActionPipelineStage::Authorization),
                new McpTestStageHandler(ActionPipelineStage::Idempotency),
                new ConfirmationStage(
                    $confirmationService,
                    new ConfirmationScopeHasher(),
                ),
                new ActionExecutionStage($executor),
                new OutputPolicyStage(),
            ],
        );

        return [
            'definition' => $definition,
            'gateway' => new McpActionGateway(
                $bus,
                new ActionResultNormalizer(),
                self::composer('trusted-user', 'trusted-tenant'),
            ),
            'confirmationService' => $confirmationService,
            'executor' => $executor,
        ];
    }

    /**
     * @return array{
     *   definition: ActionDefinition,
     *   gateway: McpActionGateway,
     *   executor: CountingMcpActionExecutor,
     *   idempotencyStore: InMemoryMcpIdempotencyStore
     * }
     */
    public static function idempotencyAuthorityRuntime(): array
    {
        $definition = new ActionDefinition(
            id: 'orders.update',
            version: 1,
            title: 'Update order',
            description: 'Exercises MCP idempotency authority.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );

        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $clock = new FixedMcpAuthorityClock();
        $store = new InMemoryMcpIdempotencyStore();
        $service = new IdempotencyService(
            $store,
            $clock,
            new IdempotencyReplayCodec(),
        );
        $executor = new CountingMcpActionExecutor();

        $bus = new ActionBus(
            $registry,
            new CapturingActionPipelineAuditor(),
            [
                new McpTestStageHandler(ActionPipelineStage::InputValidation),
                new McpTestStageHandler(ActionPipelineStage::Authorization),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $service,
                ),
                new McpTestStageHandler(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor, $service),
                new OutputPolicyStage(),
            ],
        );

        return [
            'definition' => $definition,
            'gateway' => new McpActionGateway(
                $bus,
                new ActionResultNormalizer(),
                self::composer('trusted-user', 'trusted-tenant'),
            ),
            'executor' => $executor,
            'idempotencyStore' => $store,
        ];
    }

    public static function composer(
        mixed $actor = 'trusted-user',
        mixed $tenant = 'trusted-tenant',
    ): TrustedContextComposer {
        return new TrustedContextComposer(
            new StaticResolvedValueActorResolver($actor),
            new StaticResolvedValueTenantResolver($tenant),
        );
    }

    public static function bus(
        ActionDefinition $definition,
        CapturingActionPipelineAuditor $auditor,
        mixed $executionOutput = ['executed' => true],
    ): ActionBus {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = null;
            if ($stage === ActionPipelineStage::Execution) {
                $behavior = static fn (ActionPipelineState $state): ActionPipelineState
                    => $state->withOutput($executionOutput);
            }
            $handlers[] = new McpTestStageHandler($stage, $behavior);
        }

        return new ActionBus($registry, $auditor, $handlers);
    }

    /**
     * @return array{gateway: McpActionGateway, auditor: CapturingActionPipelineAuditor}
     */
    public static function gateway(
        ActionDefinition $definition,
        mixed $actor = 'trusted-user',
        mixed $tenant = 'trusted-tenant',
        mixed $executionOutput = ['executed' => true],
    ): array {
        $auditor = new CapturingActionPipelineAuditor();

        return [
            'gateway' => new McpActionGateway(
                self::bus($definition, $auditor, $executionOutput),
                new ActionResultNormalizer(),
                self::composer($actor, $tenant),
            ),
            'auditor' => $auditor,
        ];
    }
}

final class StaticResolvedValueActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return $this->value === null
            ? null
            : new ResolvedTrustedValue(
                $this->value,
                new ContextProvenance('test.mcp.actor'),
            );
    }
}

final class StaticResolvedValueTenantResolver implements TenantResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return $this->value === null
            ? null
            : new ResolvedTrustedValue(
                $this->value,
                new ContextProvenance('test.mcp.tenant'),
            );
    }
}

final class McpTestStageHandler implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly ActionPipelineStage $pipelineStage,
        private readonly ?\Closure $behavior = null,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if ($this->behavior === null) {
            return ActionPipelineDecision::continueWith($state);
        }

        $next = ($this->behavior)($state);

        return $next instanceof ActionPipelineDecision
            ? $next
            : ActionPipelineDecision::continueWith($next);
    }
}

final class CapturingActionPipelineAuditor implements ActionPipelineAuditor
{
    /** @var list<ActionCall> */
    public array $calls = [];

    /** @var list<ActionPipelineOutcome> */
    public array $outcomes = [];

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls[] = $call;
        $this->outcomes[] = $outcome;
    }
}


final class CapturingMcpExecutionStage implements ActionPipelineStageHandler
{
    public int $calls = 0;

    public ?ActionPipelineState $lastState = null;

    public function __construct(
        private readonly mixed $output,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Execution;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->calls++;
        $this->lastState = $state;

        return ActionPipelineDecision::continueWith(
            $state->withOutput($this->output),
        );
    }
}

final class ReleasingMcpSensitiveOutputRedactor implements SensitiveOutputRedactor
{
    public function __construct(
        private readonly mixed $releasedOutput,
    ) {}

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        return OutputRedactionResult::release($this->releasedOutput);
    }
}

final class FixedMcpAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            '2026-09-18 20:00:00',
            new DateTimeZone('UTC'),
        );
    }
}

final class InMemoryMcpAuditEventStore implements AuditEventStore
{
    /** @var list<AuditEvent> */
    public array $events = [];

    public function append(AuditEvent $event): void
    {
        $this->events[] = $event;
    }
}


final class FixedMcpAuthorityClock implements ConfirmationClock, IdempotencyClock
{
    public function __construct(
        private int $timestamp = 1_800_000_000,
    ) {}

    public function now(): int
    {
        return $this->timestamp;
    }
}

final class SequentialMcpConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    private int $sequence = 0;

    public function generate(): string
    {
        $prefix = str_pad(
            base_convert((string) $this->sequence++, 10, 36),
            6,
            '0',
            STR_PAD_LEFT,
        );

        return $prefix.str_repeat('A', 37);
    }
}

final class InMemoryMcpConfirmationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];

    public function createPending(
        string $tokenHash,
        ConfirmationRecord $record,
        int $ttlSeconds,
    ): bool {
        if (isset($this->records[$tokenHash])) {
            return false;
        }

        $this->records[$tokenHash] = $record;

        return true;
    }

    public function approvePending(
        string $tokenHash,
        int $now,
        int $receiptExpiresAt,
    ): bool {
        $record = $this->records[$tokenHash] ?? null;

        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Pending
            || $now >= $record->challengeExpiresAt
        ) {
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

    public function consumeApproved(
        string $tokenHash,
        string $expectedScopeFingerprint,
        int $now,
    ): bool {
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

final class InMemoryMcpIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function find(string $keyHash): ?IdempotencyRecord
    {
        return $this->records[$keyHash] ?? null;
    }

    public function claim(
        IdempotencyRecord $fresh,
        int $now,
    ): IdempotencyStoreClaimResult {
        $existing = $this->records[$fresh->keyHash] ?? null;

        if ($existing !== null && $existing->isActiveAt($now)) {
            return IdempotencyStoreClaimResult::existing($existing);
        }

        $this->records[$fresh->keyHash] = $fresh;

        return IdempotencyStoreClaimResult::claimed($fresh);
    }

    public function complete(
        string $keyHash,
        string $intentFingerprint,
        string $outputPayload,
    ): void {
        $record = $this->records[$keyHash]
            ?? throw new \RuntimeException('Missing MCP idempotency claim.');

        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('MCP idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $record->keyHash,
            intentFingerprint: $record->intentFingerprint,
            state: IdempotencyRecordState::Completed,
            outputPayload: $outputPayload,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }

    public function markIndeterminate(
        string $keyHash,
        string $intentFingerprint,
    ): void {
        $record = $this->records[$keyHash]
            ?? throw new \RuntimeException('Missing MCP idempotency claim.');

        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('MCP idempotency intent mismatch.');
        }

        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $record->keyHash,
            intentFingerprint: $record->intentFingerprint,
            state: IdempotencyRecordState::Indeterminate,
            outputPayload: null,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }
}

final class CountingMcpActionExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function execute(
        ActionDefinition $definition,
        array $input,
        \SurfaceRelay\Laravel\Runtime\InvocationContext $context,
    ): mixed {
        $this->calls++;

        return [
            'action' => $definition->id,
            'input' => $input,
        ];
    }
}
