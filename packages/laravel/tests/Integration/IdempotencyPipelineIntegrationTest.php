<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Contracts\ActionAuthorizer;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
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
use SurfaceRelay\Laravel\Idempotency\UnreplayableIdempotencyOutput;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
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
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final class IdempotencyPipelineIntegrationTest extends TestCase
{
    public function test_lost_response_consequential_retry_replays_without_second_confirmation_or_execution(): void
    {
        $harness = new IdempotencyIntegrationHarness();
        $key = 'lost-response-key';

        $first = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            correlationId: 'corr-1',
        ));
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $first->halt?->code);
        self::assertSame(0, $harness->executor->calls);
        $challenge = $first->halt?->confirmation;
        self::assertNotNull($challenge);
        $receipt = $harness->confirmationService->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $second = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            receipt: $receipt,
            correlationId: 'corr-2',
        ));
        $secondResult = $harness->normalizer->normalize($second);
        self::assertTrue($second->completed);
        self::assertSame('succeeded', $secondResult->status->value);
        self::assertSame(1, $harness->executor->calls);

        // Simulate a lost call-2 response: the client retries the exact intent
        // with the same key but cannot reuse the already-consumed receipt.
        $third = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            correlationId: 'corr-3',
        ));
        $thirdResult = $harness->normalizer->normalize($third);

        self::assertTrue($third->completed);
        self::assertSame('succeeded', $thirdResult->status->value);
        self::assertSame('corr-3', $thirdResult->correlationId);
        self::assertSame($secondResult->data, $thirdResult->data);
        self::assertSame(3, $harness->authorizer->calls,
            'Authorization reruns for every current invocation, including completed replay.');
        self::assertSame(1, $harness->confirmationStore->consumeApprovedCalls,
            'Completed replay must not consume or require a second confirmation receipt.');
        self::assertSame(1, $harness->executor->calls,
            'The external side effect must execute exactly once across lost-response retry.');
        self::assertSame(2, $harness->outputPolicy->calls,
            'Output policy runs for original success and replayed raw output.');
        self::assertSame(3, $harness->auditor->calls);
    }

    public function test_same_partition_changes_to_exact_intent_dimensions_conflict_before_confirmation_or_execution(): void
    {
        $mutations = [
            'validated input' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                input: ['amount' => 101],
                idempotencyKey: 'same-partition-key',
            ),
            'current record' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                context: $h->context(record: 43, idempotencyKey: 'same-partition-key'),
            ),
            'current selection' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                context: $h->context(selection: [10, 12], idempotencyKey: 'same-partition-key'),
            ),
            'browser session' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                context: $h->context(session: 'session-B', idempotencyKey: 'same-partition-key'),
            ),
        ];

        foreach ($mutations as $name => $mutate) {
            $harness = new IdempotencyIntegrationHarness();
            $harness->executeConsequential('same-partition-key');
            $executorCalls = $harness->executor->calls;
            $confirmationConsumes = $harness->confirmationStore->consumeApprovedCalls;

            $outcome = $harness->bus->dispatch($mutate($harness));

            self::assertFalse($outcome->completed, $name);
            self::assertSame(ActionPipelineStage::Idempotency, $outcome->haltedAt, $name);
            self::assertSame(CoreActionErrorCode::IDEMPOTENCY_CONFLICT, $outcome->halt?->code, $name);
            self::assertSame($executorCalls, $harness->executor->calls, $name);
            self::assertSame($confirmationConsumes, $harness->confirmationStore->consumeApprovedCalls, $name);
        }
    }

    public function test_trusted_partition_or_action_identity_changes_allow_independent_reuse_of_same_raw_key(): void
    {
        $cases = [
            'actor partition' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                context: $h->context(actor: 'user-B', idempotencyKey: 'partition-reuse-key'),
            ),
            'tenant partition' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                context: $h->context(tenant: 'tenant-B', idempotencyKey: 'partition-reuse-key'),
            ),
            'action id' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                actionId: 'orders.refund_alt',
                idempotencyKey: 'partition-reuse-key',
            ),
            'action version' => static fn (IdempotencyIntegrationHarness $h): ActionCall => $h->call(
                actionVersion: 2,
                idempotencyKey: 'partition-reuse-key',
            ),
        ];

        foreach ($cases as $name => $mutate) {
            $harness = new IdempotencyIntegrationHarness();
            $harness->executeConsequential('partition-reuse-key');

            $candidate = $mutate($harness);
            $challengeOutcome = $harness->bus->dispatch($candidate);
            self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $challengeOutcome->halt?->code, $name);
            $challenge = $challengeOutcome->halt?->confirmation;
            self::assertNotNull($challenge, $name);
            $receipt = $harness->confirmationService->approveChallenge($challenge->challengeId);
            self::assertNotNull($receipt, $name);

            $success = $harness->bus->dispatch(new ActionCall(
                actionId: $candidate->actionId,
                actionVersion: $candidate->actionVersion,
                input: $candidate->input,
                context: $candidate->context,
                bindingId: $candidate->bindingId,
                confirmationReceipt: $receipt,
            ));

            self::assertTrue($success->completed, $name);
            self::assertSame(2, $harness->executor->calls, $name);
        }
    }

    public function test_surface_or_binding_change_alone_replays_same_completed_intent(): void
    {
        foreach (['surface', 'binding'] as $dimension) {
            $harness = new IdempotencyIntegrationHarness();
            $key = 'excluded-dimension-key';
            $harness->executeConsequential($key);

            $context = $dimension === 'surface'
                ? $harness->context(
                    surface: 'alternate-surface',
                    correlationId: 'corr-replay',
                    idempotencyKey: $key,
                )
                : $harness->context(
                    correlationId: 'corr-replay',
                    idempotencyKey: $key,
                );
            $outcome = $harness->bus->dispatch($harness->call(
                context: $context,
                bindingId: $dimension === 'binding' ? 'binding-B' : 'binding-A',
                correlationId: 'corr-replay',
            ));

            self::assertTrue($outcome->completed, $dimension);
            self::assertSame(1, $harness->executor->calls, $dimension);
            self::assertSame('corr-replay', $outcome->state->context->correlationId, $dimension);
        }
    }

    public function test_authorization_denial_precedes_completed_replay(): void
    {
        $harness = new IdempotencyIntegrationHarness();
        $key = 'authorization-before-replay';
        $harness->executeConsequential($key);
        $harness->authorizer->allows = false;

        $outcome = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            correlationId: 'corr-denied',
        ));

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Authorization, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::AUTHORIZATION_DENIED, $outcome->halt?->code);
        self::assertSame(1, $harness->executor->calls);
        self::assertSame(1, $harness->outputPolicy->calls);
    }

    public function test_required_missing_key_halts_before_confirmation_and_execution(): void
    {
        $harness = new IdempotencyIntegrationHarness();

        $outcome = $harness->bus->dispatch($harness->call(idempotencyKey: null));

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Idempotency, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_KEY_REQUIRED, $outcome->halt?->code);
        self::assertSame(0, $harness->confirmationStore->consumeApprovedCalls);
        self::assertSame(0, $harness->executor->calls);
    }

    public function test_in_progress_and_indeterminate_retries_fail_closed_before_confirmation_or_execution(): void
    {
        // Completion persistence failure leaves an active in_progress claim.
        $inProgress = new IdempotencyIntegrationHarness();
        $inProgress->idempotencyStore->completeThrows = true;
        $receipt = $inProgress->approveFor('active-in-progress');
        try {
            $inProgress->bus->dispatch($inProgress->call(
                idempotencyKey: 'active-in-progress',
                receipt: $receipt,
            ));
            self::fail('Expected completion persistence failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('integration completion persistence failed', $e->getMessage());
        }
        $inProgress->idempotencyStore->completeThrows = false;
        $retry = $inProgress->bus->dispatch($inProgress->call(idempotencyKey: 'active-in-progress'));
        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS, $retry->halt?->code);
        self::assertSame(1, $inProgress->executor->calls);
        self::assertSame(1, $inProgress->confirmationStore->consumeApprovedCalls);

        // Executor failure leaves an active indeterminate claim.
        $indeterminate = new IdempotencyIntegrationHarness();
        $indeterminate->executor->throwOnExecute = true;
        $receipt = $indeterminate->approveFor('active-indeterminate');
        try {
            $indeterminate->bus->dispatch($indeterminate->call(
                idempotencyKey: 'active-indeterminate',
                receipt: $receipt,
            ));
            self::fail('Expected executor failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('integration executor failed after side-effect boundary', $e->getMessage());
        }
        $indeterminate->executor->throwOnExecute = false;
        $retry = $indeterminate->bus->dispatch($indeterminate->call(idempotencyKey: 'active-indeterminate'));
        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE, $retry->halt?->code);
        self::assertSame(1, $indeterminate->executor->calls);
        self::assertSame(1, $indeterminate->confirmationStore->consumeApprovedCalls);
    }

    public function test_policy_none_executes_twice_for_two_calls_even_with_same_key(): void
    {
        $harness = new IdempotencyIntegrationHarness();

        $first = $harness->bus->dispatch($harness->call(
            actionId: 'orders.preview',
            idempotencyKey: 'ignored-by-none-policy',
            correlationId: 'corr-none-1',
        ));
        $second = $harness->bus->dispatch($harness->call(
            actionId: 'orders.preview',
            idempotencyKey: 'ignored-by-none-policy',
            correlationId: 'corr-none-2',
        ));

        self::assertTrue($first->completed);
        self::assertTrue($second->completed);
        self::assertSame(2, $harness->executor->calls);
    }

    public function test_executor_side_effect_then_throw_never_reexecutes_same_active_key(): void
    {
        $harness = new IdempotencyIntegrationHarness();
        $harness->executor->throwOnExecute = true;
        $receipt = $harness->approveFor('throw-after-side-effect');

        try {
            $harness->bus->dispatch($harness->call(
                idempotencyKey: 'throw-after-side-effect',
                receipt: $receipt,
            ));
            self::fail('Expected executor failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('integration executor failed after side-effect boundary', $e->getMessage());
        }

        $harness->executor->throwOnExecute = false;
        $retry = $harness->bus->dispatch($harness->call(idempotencyKey: 'throw-after-side-effect'));

        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE, $retry->halt?->code);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_unreplayable_successful_output_becomes_indeterminate_and_does_not_reopen(): void
    {
        $harness = new IdempotencyIntegrationHarness();
        $harness->executor->result = new \stdClass();
        $receipt = $harness->approveFor('unreplayable-output');

        try {
            $harness->bus->dispatch($harness->call(
                idempotencyKey: 'unreplayable-output',
                receipt: $receipt,
            ));
            self::fail('Expected replay codec refusal.');
        } catch (UnreplayableIdempotencyOutput) {
        }

        $harness->executor->result = ['amount' => 100];
        $retry = $harness->bus->dispatch($harness->call(idempotencyKey: 'unreplayable-output'));
        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE, $retry->halt?->code);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_completion_persistence_failure_never_returns_success_and_retry_stays_in_progress(): void
    {
        $harness = new IdempotencyIntegrationHarness();
        $harness->idempotencyStore->completeThrows = true;
        $receipt = $harness->approveFor('complete-fails');

        try {
            $harness->bus->dispatch($harness->call(
                idempotencyKey: 'complete-fails',
                receipt: $receipt,
            ));
            self::fail('Expected completion persistence failure.');
        } catch (\RuntimeException $e) {
            self::assertSame('integration completion persistence failed', $e->getMessage());
        }

        $harness->idempotencyStore->completeThrows = false;
        $retry = $harness->bus->dispatch($harness->call(idempotencyKey: 'complete-fails'));
        self::assertFalse($retry->completed);
        self::assertSame(CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS, $retry->halt?->code);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_exact_retention_expiry_equality_permits_new_claim_and_second_execution(): void
    {
        $harness = new IdempotencyIntegrationHarness(retentionSeconds: 60);
        $key = 'bounded-retention';
        $harness->executeConsequential($key);
        self::assertSame(1, $harness->executor->calls);

        $harness->clock->advance(60);
        $fresh = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            correlationId: 'corr-after-expiry',
        ));
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $fresh->halt?->code,
            'At exact expiresAt the bounded idempotency guarantee has ended.');
        $challenge = $fresh->halt?->confirmation;
        self::assertNotNull($challenge);
        $receipt = $harness->confirmationService->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $second = $harness->bus->dispatch($harness->call(
            idempotencyKey: $key,
            receipt: $receipt,
            correlationId: 'corr-second-execution',
        ));

        self::assertTrue($second->completed);
        self::assertSame(2, $harness->executor->calls);
    }
}

final class IdempotencyIntegrationHarness
{
    public readonly ActionDefinition $definition;
    public readonly IdempotencyIntegrationClock $clock;
    public readonly IdempotencyIntegrationStore $idempotencyStore;
    public readonly IdempotencyService $idempotencyService;
    public readonly IdempotencyIntegrationConfirmationStore $confirmationStore;
    public readonly ConfirmationService $confirmationService;
    public readonly IdempotencyIntegrationAuthorizer $authorizer;
    public readonly IdempotencyIntegrationExecutor $executor;
    public readonly IdempotencyIntegrationOutputPolicy $outputPolicy;
    public readonly IdempotencyIntegrationAuditor $auditor;
    public readonly ActionResultNormalizer $normalizer;
    public readonly ActionBus $bus;

    public function __construct(int $retentionSeconds = 86400)
    {
        $definitions = [
            $this->definition = $this->definition('orders.refund', 1, IdempotencyPolicy::RequiredKey, ActionRisk::Consequential),
            $this->definition('orders.refund_alt', 1, IdempotencyPolicy::RequiredKey, ActionRisk::Consequential),
            $this->definition('orders.refund', 2, IdempotencyPolicy::RequiredKey, ActionRisk::Consequential),
            $this->definition('orders.preview', 1, IdempotencyPolicy::None, ActionRisk::Moderate),
        ];

        $registry = new InMemoryActionRegistry();
        $rules = new InMemoryActionValidationRules();
        foreach ($definitions as $definition) {
            $registry->register($definition);
            $rules->register($definition, ['amount' => ['required', 'integer']]);
        }

        $this->clock = new IdempotencyIntegrationClock(strtotime('2026-09-09T08:00:00Z'));
        $this->idempotencyStore = new IdempotencyIntegrationStore();
        $this->idempotencyService = new IdempotencyService(
            $this->idempotencyStore,
            $this->clock,
            new IdempotencyReplayCodec(),
            $retentionSeconds,
        );
        $this->confirmationStore = new IdempotencyIntegrationConfirmationStore();
        $this->confirmationService = new ConfirmationService(
            $this->confirmationStore,
            $this->clock,
            new IdempotencyIntegrationTokenGenerator(),
        );
        $this->authorizer = new IdempotencyIntegrationAuthorizer();
        $this->executor = new IdempotencyIntegrationExecutor();
        $this->outputPolicy = new IdempotencyIntegrationOutputPolicy();
        $this->auditor = new IdempotencyIntegrationAuditor();
        $this->normalizer = new ActionResultNormalizer();

        $validatorFactory = new ValidatorFactory(new Translator(new ArrayLoader(), 'en'));
        $this->bus = new ActionBus(
            $registry,
            $this->auditor,
            [
                new LaravelInputValidationStage($validatorFactory, $rules),
                new AuthorizationStage($this->authorizer),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $this->idempotencyService,
                ),
                new ConfirmationStage($this->confirmationService, new ConfirmationScopeHasher()),
                new ActionExecutionStage($this->executor, $this->idempotencyService),
                $this->outputPolicy,
            ],
        );
    }

    public function executeConsequential(string $key): ActionPipelineOutcome
    {
        $receipt = $this->approveFor($key);
        $outcome = $this->bus->dispatch($this->call(
            idempotencyKey: $key,
            receipt: $receipt,
            correlationId: 'corr-execute',
        ));
        Assert::assertTrue($outcome->completed);
        return $outcome;
    }

    public function approveFor(string $key): string
    {
        $pending = $this->bus->dispatch($this->call(
            idempotencyKey: $key,
            correlationId: 'corr-challenge',
        ));
        Assert::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $pending->halt?->code);
        $challenge = $pending->halt?->confirmation;
        Assert::assertNotNull($challenge);
        $receipt = $this->confirmationService->approveChallenge($challenge->challengeId);
        Assert::assertNotNull($receipt);
        return $receipt;
    }

    public function call(
        string $actionId = 'orders.refund',
        int $actionVersion = 1,
        array $input = ['amount' => 100],
        ?InvocationContext $context = null,
        string $bindingId = 'binding-A',
        ?string $receipt = null,
        string $correlationId = 'corr-default',
        ?string $idempotencyKey = 'default-key',
    ): ActionCall {
        return new ActionCall(
            actionId: $actionId,
            actionVersion: $actionVersion,
            input: $input,
            context: $context ?? $this->context(
                correlationId: $correlationId,
                idempotencyKey: $idempotencyKey,
            ),
            bindingId: $bindingId,
            confirmationReceipt: $receipt,
        );
    }

    public function context(
        string $actor = 'user-A',
        string $tenant = 'tenant-A',
        int $record = 42,
        array $selection = [10, 11],
        string $session = 'session-A',
        string $surface = 'webmcp',
        string $correlationId = 'corr-context',
        ?string $idempotencyKey = null,
    ): InvocationContext {
        return new InvocationContext(
            surface: $surface,
            correlationId: $correlationId,
            trustedContext: [
                $this->entry(ContextRequirement::AuthenticatedActor, $actor),
                $this->entry(ContextRequirement::Tenant, $tenant),
                $this->entry(ContextRequirement::CurrentRecord, $record),
                $this->entry(ContextRequirement::CurrentSelection, $selection),
                $this->entry(ContextRequirement::BrowserSession, $session),
            ],
            idempotencyKey: $idempotencyKey,
        );
    }

    private function definition(
        string $id,
        int $version,
        IdempotencyPolicy $policy,
        ActionRisk $risk,
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Order operation',
            description: 'Exercises the exact production-trust pipeline.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: $risk === ActionRisk::Consequential
                ? ActionEffect::ExternalSideEffect
                : ActionEffect::ReversibleWrite,
            risk: $risk,
            idempotency: $policy,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
            ],
        );
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry(
            $requirement,
            $value,
            new ContextProvenance('integration.trusted-runtime'),
        );
    }
}

final class IdempotencyIntegrationClock implements IdempotencyClock, ConfirmationClock
{
    public function __construct(private int $timestamp) {}

    public function now(): int
    {
        return $this->timestamp;
    }

    public function advance(int $seconds): void
    {
        $this->timestamp += $seconds;
    }
}

final class IdempotencyIntegrationStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    public array $records = [];
    public bool $completeThrows = false;

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
        if ($this->completeThrows) {
            throw new \RuntimeException('integration completion persistence failed');
        }

        $record = $this->records[$keyHash] ?? throw new \RuntimeException('missing integration idempotency claim');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('integration idempotency intent mismatch');
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
        $record = $this->records[$keyHash] ?? throw new \RuntimeException('missing integration idempotency claim');
        if (!hash_equals($record->intentFingerprint, $intentFingerprint)) {
            throw new \RuntimeException('integration idempotency intent mismatch');
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
}

final class IdempotencyIntegrationConfirmationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];
    public int $consumeApprovedCalls = 0;

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
        $this->consumeApprovedCalls++;
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

final class IdempotencyIntegrationTokenGenerator implements ConfirmationTokenGenerator
{
    private int $sequence = 0;

    public function generate(): string
    {
        $prefix = str_pad(base_convert((string) $this->sequence++, 10, 36), 6, '0', STR_PAD_LEFT);
        return $prefix . str_repeat('A', 37);
    }
}

final class IdempotencyIntegrationAuthorizer implements ActionAuthorizer
{
    public int $calls = 0;
    public bool $allows = true;

    public function allows(ActionDefinition $definition, array $input, InvocationContext $context): bool
    {
        $this->calls++;
        return $this->allows;
    }
}

final class IdempotencyIntegrationExecutor implements ActionExecutor
{
    public int $calls = 0;
    public bool $throwOnExecute = false;
    public mixed $result = ['amount' => 100];

    public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
    {
        $this->calls++;
        if ($this->throwOnExecute) {
            throw new \RuntimeException('integration executor failed after side-effect boundary');
        }
        return $this->result;
    }
}

final class IdempotencyIntegrationOutputPolicy implements ActionPipelineStageHandler
{
    public int $calls = 0;

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::OutputPolicy;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->calls++;
        return ActionPipelineDecision::continueWith($state->withOutput([
            'projected' => $state->output,
        ]));
    }
}

final class IdempotencyIntegrationAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
    }
}
