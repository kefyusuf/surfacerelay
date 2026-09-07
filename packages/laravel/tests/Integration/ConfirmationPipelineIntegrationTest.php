<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Confirmation\VerifiedConfirmation;
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

final class ConfirmationPipelineIntegrationTest extends TestCase
{
    public function test_authorization_denial_happens_before_challenge_issuance(): void
    {
        $harness = new ConfirmationIntegrationHarness(authorizerAllows: false);

        $outcome = $harness->bus->dispatch($harness->call());

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Authorization, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::AUTHORIZATION_DENIED, $outcome->halt?->code);
        self::assertSame(0, $harness->tokens->calls);
        self::assertSame(0, $harness->store->createPendingCalls);
        self::assertSame(0, $harness->executor->calls);
        self::assertSame('rejected', $harness->normalizer->normalize($outcome)->status->value);
    }

    public function test_validation_strips_unvalidated_input_before_confirmation_scope_is_recorded(): void
    {
        $harness = new ConfirmationIntegrationHarness();
        $first = $harness->bus->dispatch($harness->call(input: [
            'amount' => 100,
            'ignored' => 'first-value',
        ]));
        $challenge = $first->halt?->confirmation;
        self::assertNotNull($challenge);
        $receipt = $harness->service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $second = $harness->bus->dispatch($harness->call(
            input: ['ignored' => 'different-value', 'amount' => 100],
            receipt: $receipt,
            correlationId: 'corr-validation-2',
        ));

        self::assertTrue($second->completed,
            'Unvalidated caller fields must be removed before the confirmation scope is fingerprinted.');
        self::assertSame(['amount' => 100], $harness->authorizer->lastInput);
        self::assertSame(1, $harness->executor->calls);
    }

    public function test_consequential_flow_challenges_then_executes_once_and_replay_challenges_again(): void
    {
        $harness = new ConfirmationIntegrationHarness();
        $context1 = $harness->context(correlationId: 'corr-first', idempotencyKey: 'idem-first');

        $first = $harness->bus->dispatch($harness->call(context: $context1));
        $firstResult = $harness->normalizer->normalize($first);

        self::assertSame('confirmation_required', $firstResult->status->value);
        self::assertSame(0, $harness->executor->calls);
        $challenge = $firstResult->confirmation;
        self::assertNotNull($challenge);

        $scope = (new ConfirmationScopeHasher())->fingerprint(new ActionPipelineState(
            definition: $harness->definition,
            input: ['amount' => 100],
            context: $context1,
            bindingId: 'binding-A',
        ));
        $pendingJson = json_encode($firstResult->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString(hash('sha256', $challenge->challengeId), $pendingJson);
        self::assertStringNotContainsString($scope, $pendingJson);

        $receipt = $harness->service->approveChallenge($challenge->challengeId);
        self::assertSame($challenge->challengeId, $receipt);

        $second = $harness->bus->dispatch($harness->call(
            receipt: $receipt,
            correlationId: 'corr-second',
            idempotencyKey: 'different-retry-key',
        ));
        $secondResult = $harness->normalizer->normalize($second);

        self::assertTrue($second->completed);
        self::assertSame('succeeded', $secondResult->status->value);
        self::assertSame(1, $harness->executor->calls);
        $confirmation = $harness->executor->lastContext?->require(ContextRequirement::HumanConfirmation);
        self::assertNotNull($confirmation);
        self::assertInstanceOf(VerifiedConfirmation::class, $confirmation->value);
        self::assertSame('surfacerelay.confirmation', $confirmation->provenance->provider);
        self::assertNull($confirmation->provenance->reference);

        $successJson = json_encode($secondResult->toArray(), JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($receipt, $successJson);
        self::assertStringNotContainsString(hash('sha256', $receipt), $successJson);
        self::assertStringNotContainsString($scope, $successJson);

        $replay = $harness->bus->dispatch($harness->call(
            receipt: $receipt,
            correlationId: 'corr-replay',
        ));
        self::assertFalse($replay->completed);
        self::assertSame(ActionPipelineStage::Confirmation, $replay->haltedAt);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $replay->halt?->code);
        self::assertNotSame($receipt, $replay->halt?->confirmation?->challengeId);
        self::assertSame(1, $harness->executor->calls,
            'A consumed receipt must never execute the consequential action twice.');
    }

    public function test_receipt_remains_spent_when_execution_throws_after_consumption(): void
    {
        $harness = new ConfirmationIntegrationHarness();
        $challengeOutcome = $harness->bus->dispatch($harness->call());
        $challenge = $challengeOutcome->halt?->confirmation;
        self::assertNotNull($challenge);
        $receipt = $harness->service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $harness->executor->throwOnExecute = true;
        try {
            $harness->bus->dispatch($harness->call(receipt: $receipt, correlationId: 'corr-throws'));
            self::fail('Expected the application executor to fail after receipt consumption.');
        } catch (\RuntimeException $exception) {
            self::assertSame('integration executor failed', $exception->getMessage());
        }

        $harness->executor->throwOnExecute = false;
        $retry = $harness->bus->dispatch($harness->call(receipt: $receipt, correlationId: 'corr-after-failure'));

        self::assertFalse($retry->completed);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $retry->halt?->code);
        self::assertSame(1, $harness->executor->calls,
            'Downstream failure must not restore a confirmation capability already consumed.');
    }

    public function test_receipt_expiry_equality_fails_closed_in_the_real_pipeline(): void
    {
        $harness = new ConfirmationIntegrationHarness();
        $first = $harness->bus->dispatch($harness->call());
        $challenge = $first->halt?->confirmation;
        self::assertNotNull($challenge);
        $receipt = $harness->service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $harness->clock->advance(120);
        $expired = $harness->bus->dispatch($harness->call(receipt: $receipt, correlationId: 'corr-expired'));

        self::assertFalse($expired->completed);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $expired->halt?->code);
        self::assertSame(0, $harness->executor->calls);
    }

    public function test_each_exact_scope_dimension_mismatch_challenges_without_spending_original_receipt(): void
    {
        $cases = [
            'action id' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(actionId: 'orders.refund_alt'),
            'action version' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(actionVersion: 2),
            'validated input' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(input: ['amount' => 101]),
            'actor' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(context: $h->context(actor: 'user-B')),
            'tenant' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(context: $h->context(tenant: 'tenant-B')),
            'binding' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(bindingId: 'binding-B'),
            'current record' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(context: $h->context(record: 43)),
            'current selection' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(context: $h->context(selection: [10, 12])),
            'browser session' => static fn (ConfirmationIntegrationHarness $h): ActionCall => $h->call(context: $h->context(session: 'session-B')),
        ];

        foreach ($cases as $name => $mutatedCall) {
            $harness = new ConfirmationIntegrationHarness();
            $first = $harness->bus->dispatch($harness->call());
            $challenge = $first->halt?->confirmation;
            self::assertNotNull($challenge, $name);
            $receipt = $harness->service->approveChallenge($challenge->challengeId);
            self::assertNotNull($receipt, $name);

            $wrong = $mutatedCall($harness);
            $wrong = new ActionCall(
                actionId: $wrong->actionId,
                actionVersion: $wrong->actionVersion,
                input: $wrong->input,
                context: $wrong->context,
                bindingId: $wrong->bindingId,
                confirmationReceipt: $receipt,
            );
            $wrongOutcome = $harness->bus->dispatch($wrong);

            self::assertFalse($wrongOutcome->completed, $name);
            self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $wrongOutcome->halt?->code, $name);
            self::assertSame(0, $harness->executor->calls, $name);

            $exact = $harness->bus->dispatch($harness->call(
                receipt: $receipt,
                correlationId: 'corr-exact-after-' . str_replace(' ', '-', $name),
            ));
            self::assertTrue($exact->completed,
                $name . ': wrong-scope attempt must leave the original exact receipt usable.');
            self::assertSame(1, $harness->executor->calls, $name);
        }
    }

    public function test_caller_boolean_and_metadata_cannot_manufacture_confirmation_authority(): void
    {
        $harness = new ConfirmationIntegrationHarness();
        $context = $harness->context(
            metadata: [
                'human_confirmation' => true,
                'confirmationReceipt' => ConfirmationIntegrationHarness::TOKEN_A,
                'confirmed' => true,
            ],
        );

        self::assertFalse($context->has(ContextRequirement::HumanConfirmation));
        $outcome = $harness->bus->dispatch($harness->call(
            input: [
                'amount' => 100,
                'confirmed' => true,
                'human_confirmation' => 'caller-value',
            ],
            context: $context,
            receipt: null,
        ));

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Confirmation, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::CONFIRMATION_REQUIRED, $outcome->halt?->code);
        self::assertSame(0, $harness->executor->calls);
    }
}

final class ConfirmationIntegrationHarness
{
    public const string TOKEN_A = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
    public const string TOKEN_B = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';
    public const string TOKEN_C = 'CCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCCC';
    public const string TOKEN_D = 'DDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDDD';
    public const string TOKEN_E = 'EEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEEE';

    public readonly ActionDefinition $definition;
    public readonly ConfirmationIntegrationStore $store;
    public readonly ConfirmationIntegrationClock $clock;
    public readonly ConfirmationIntegrationTokenGenerator $tokens;
    public readonly ConfirmationService $service;
    public readonly ConfirmationIntegrationAuthorizer $authorizer;
    public readonly ConfirmationIntegrationExecutor $executor;
    public readonly ActionBus $bus;
    public readonly ActionResultNormalizer $normalizer;

    public function __construct(bool $authorizerAllows = true)
    {
        $this->definition = $this->definition('orders.refund', 1);
        $alternate = $this->definition('orders.refund_alt', 1);
        $versionTwo = $this->definition('orders.refund', 2);

        $registry = new InMemoryActionRegistry();
        foreach ([$this->definition, $alternate, $versionTwo] as $definition) {
            $registry->register($definition);
        }

        $rules = new InMemoryActionValidationRules();
        foreach ([$this->definition, $alternate, $versionTwo] as $definition) {
            $rules->register($definition, [
                'amount' => ['required', 'integer'],
            ]);
        }

        $validatorFactory = new ValidatorFactory(new Translator(new ArrayLoader(), 'en'));
        $this->store = new ConfirmationIntegrationStore();
        $this->clock = new ConfirmationIntegrationClock(strtotime('2026-09-07T12:00:00Z'));
        $this->tokens = new ConfirmationIntegrationTokenGenerator([
            self::TOKEN_A,
            self::TOKEN_B,
            self::TOKEN_C,
            self::TOKEN_D,
            self::TOKEN_E,
        ]);
        $this->service = new ConfirmationService($this->store, $this->clock, $this->tokens);
        $this->authorizer = new ConfirmationIntegrationAuthorizer($authorizerAllows);
        $this->executor = new ConfirmationIntegrationExecutor();
        $this->normalizer = new ActionResultNormalizer();

        $this->bus = new ActionBus(
            $registry,
            new ConfirmationIntegrationAuditor(),
            [
                new LaravelInputValidationStage($validatorFactory, $rules),
                new AuthorizationStage($this->authorizer),
                new ConfirmationStage($this->service, new ConfirmationScopeHasher()),
                new ConfirmationIntegrationPassthroughStage(ActionPipelineStage::Idempotency),
                new ActionExecutionStage($this->executor),
                new ConfirmationIntegrationPassthroughStage(ActionPipelineStage::OutputPolicy),
            ],
        );
    }

    public function call(
        string $actionId = 'orders.refund',
        int $actionVersion = 1,
        array $input = ['amount' => 100],
        ?InvocationContext $context = null,
        ?string $bindingId = 'binding-A',
        ?string $receipt = null,
        string $correlationId = 'corr-default',
        ?string $idempotencyKey = null,
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
        string $correlationId = 'corr-context',
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InvocationContext {
        return new InvocationContext(
            surface: 'webmcp',
            correlationId: $correlationId,
            trustedContext: [
                $this->entry(ContextRequirement::AuthenticatedActor, $actor),
                $this->entry(ContextRequirement::Tenant, $tenant),
                $this->entry(ContextRequirement::CurrentRecord, $record),
                $this->entry(ContextRequirement::CurrentSelection, $selection),
                $this->entry(ContextRequirement::BrowserSession, $session),
            ],
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Approve refund',
            description: 'Approves the exact scoped refund intent.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::None,
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

final class ConfirmationIntegrationClock implements ConfirmationClock
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

final class ConfirmationIntegrationTokenGenerator implements ConfirmationTokenGenerator
{
    public int $calls = 0;

    /** @param list<string> $tokens */
    public function __construct(private array $tokens) {}

    public function generate(): string
    {
        $token = $this->tokens[$this->calls] ?? throw new \RuntimeException('Integration token queue exhausted.');
        $this->calls++;
        return $token;
    }
}

final class ConfirmationIntegrationStore implements ConfirmationStore
{
    /** @var array<string, ConfirmationRecord> */
    private array $records = [];

    public int $createPendingCalls = 0;

    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool
    {
        $this->createPendingCalls++;
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

final class ConfirmationIntegrationAuthorizer implements ActionAuthorizer
{
    /** @var array<string, mixed>|null */
    public ?array $lastInput = null;

    public function __construct(public bool $allows = true) {}

    public function allows(ActionDefinition $definition, array $input, InvocationContext $context): bool
    {
        $this->lastInput = $input;
        return $this->allows;
    }
}

final class ConfirmationIntegrationExecutor implements ActionExecutor
{
    public int $calls = 0;
    public bool $throwOnExecute = false;
    public ?InvocationContext $lastContext = null;

    public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
    {
        $this->calls++;
        $this->lastContext = $context;
        if ($this->throwOnExecute) {
            throw new \RuntimeException('integration executor failed');
        }

        return ['amount' => $input['amount'] ?? null];
    }
}

final readonly class ConfirmationIntegrationPassthroughStage implements ActionPipelineStageHandler
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

final class ConfirmationIntegrationAuditor implements ActionPipelineAuditor
{
    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
    }
}
