<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\UnrepresentableIdempotencyScope;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class IdempotencyHashingTest extends TestCase
{
    public function test_idempotency_hashing_types_are_available(): void
    {
        $this->assertTypesAvailable();
    }

    public function test_key_validator_enforces_exact_runtime_length_without_normalization(): void
    {
        $this->assertTypesAvailable();
        $validator = new IdempotencyKeyValidator();

        self::assertTrue($validator->isValid('idem-1'));
        self::assertTrue($validator->isValid(' idem-1 '));
        self::assertTrue($validator->isValid(str_repeat('x', 240)));
        self::assertFalse($validator->isValid(''));
        self::assertFalse($validator->isValid(str_repeat('x', 241)));
    }

    public function test_lookup_hash_is_partitioned_by_action_and_trusted_authority_not_business_target(): void
    {
        $this->assertTypesAvailable();
        $hasher = new IdempotencyKeyHasher();
        $base = $hasher->hash($this->state(), 'retry-key');

        self::assertSame(
            $base,
            $hasher->hash($this->state(record: 'record-2', selection: ['order-2']), 'retry-key'),
            'Record/selection belong to intent, not lookup partition.',
        );
        self::assertNotSame($base, $hasher->hash($this->state(actor: 'actor-2'), 'retry-key'));
        self::assertNotSame($base, $hasher->hash($this->state(tenant: 'tenant-2'), 'retry-key'));
        self::assertNotSame($base, $hasher->hash($this->state(actionId: 'orders.refund.execute'), 'retry-key'));
        self::assertNotSame($base, $hasher->hash($this->state(actionVersion: 2), 'retry-key'));
    }

    public function test_browser_session_partitions_only_when_actor_and_tenant_are_both_absent(): void
    {
        $this->assertTypesAvailable();
        $hasher = new IdempotencyKeyHasher();

        $authenticatedA = $hasher->hash($this->state(session: 'session-1'), 'retry-key');
        $authenticatedB = $hasher->hash($this->state(session: 'session-2'), 'retry-key');
        self::assertSame($authenticatedA, $authenticatedB);

        $anonymousA = $hasher->hash($this->state(actor: null, tenant: null, session: 'session-1'), 'retry-key');
        $anonymousB = $hasher->hash($this->state(actor: null, tenant: null, session: 'session-2'), 'retry-key');
        self::assertNotSame($anonymousA, $anonymousB);

        $globalA = $hasher->hash($this->state(actor: null, tenant: null, session: null, surface: 'webmcp'), 'retry-key');
        $globalB = $hasher->hash($this->state(actor: null, tenant: null, session: null, surface: 'headless', bindingId: null), 'retry-key');
        self::assertSame($globalA, $globalB);
    }

    public function test_intent_fingerprint_binds_validated_business_context_but_not_retry_transport_metadata(): void
    {
        $this->assertTypesAvailable();
        $hasher = new IdempotencyIntentHasher();
        $base = $hasher->fingerprint($this->state());

        self::assertNotSame($base, $hasher->fingerprint($this->state(input: ['amount' => 101])));
        self::assertNotSame($base, $hasher->fingerprint($this->state(record: 'record-2')));
        self::assertNotSame($base, $hasher->fingerprint($this->state(selection: ['order-2'])));
        self::assertNotSame($base, $hasher->fingerprint($this->state(session: 'session-2')));

        self::assertSame($base, $hasher->fingerprint($this->state(surface: 'headless')));
        self::assertSame($base, $hasher->fingerprint($this->state(bindingId: 'binding-2')));
        self::assertSame($base, $hasher->fingerprint($this->state(correlationId: 'corr-2')));
        self::assertSame($base, $hasher->fingerprint($this->state(idempotencyKey: 'another-key')));
        self::assertSame($base, $hasher->fingerprint($this->state(confirmationReceipt: 'another-receipt')));
        self::assertSame($base, $hasher->fingerprint($this->state(metadata: ['different' => 'diagnostic'])));
        self::assertSame($base, $hasher->fingerprint($this->state(humanConfirmation: true)));
    }

    public function test_unrepresentable_trusted_scope_fails_without_value_leakage(): void
    {
        $this->assertTypesAvailable();
        $hasher = new IdempotencyIntentHasher();

        try {
            $hasher->fingerprint($this->state(actor: new IdempotencyScopeObject('secret-actor-value')));
            self::fail('Arbitrary actor object without a stable scope key must fail closed.');
        } catch (UnrepresentableIdempotencyScope $e) {
            self::assertStringNotContainsString('secret-actor-value', $e->getMessage());
        }
    }

    private function state(
        string $actionId = 'orders.refund.commit',
        int $actionVersion = 1,
        array $input = ['amount' => 100],
        mixed $actor = 'actor-1',
        mixed $tenant = 'tenant-1',
        mixed $record = 'record-1',
        mixed $selection = ['order-1'],
        mixed $session = 'session-1',
        string $surface = 'webmcp',
        ?string $bindingId = 'binding-1',
        string $correlationId = 'corr-1',
        ?string $idempotencyKey = 'idem-1',
        ?string $confirmationReceipt = 'receipt-1',
        array $metadata = ['locale' => 'tr-TR'],
        bool $humanConfirmation = false,
    ): ActionPipelineState {
        $trusted = [];
        if ($actor !== null) {
            $trusted[] = $this->entry(ContextRequirement::AuthenticatedActor, $actor);
        }
        if ($tenant !== null) {
            $trusted[] = $this->entry(ContextRequirement::Tenant, $tenant);
        }
        if ($record !== null) {
            $trusted[] = $this->entry(ContextRequirement::CurrentRecord, $record);
        }
        if ($selection !== null) {
            $trusted[] = $this->entry(ContextRequirement::CurrentSelection, $selection);
        }
        if ($session !== null) {
            $trusted[] = $this->entry(ContextRequirement::BrowserSession, $session);
        }
        if ($humanConfirmation) {
            $trusted[] = $this->entry(ContextRequirement::HumanConfirmation, 'runtime-only-marker');
        }

        return new ActionPipelineState(
            definition: $this->definition($actionId, $actionVersion),
            input: $input,
            context: new InvocationContext(
                surface: $surface,
                correlationId: $correlationId,
                trustedContext: $trusted,
                idempotencyKey: $idempotencyKey,
                metadata: $metadata,
            ),
            bindingId: $bindingId,
            confirmationReceipt: $confirmationReceipt,
        );
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Commit refund',
            description: 'Commits an approved refund.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry(
            $requirement,
            $value,
            new ContextProvenance('test.' . $requirement->value),
        );
    }

    private function assertTypesAvailable(): void
    {
        foreach ([
            IdempotencyKeyValidator::class,
            IdempotencyKeyHasher::class,
            IdempotencyIntentHasher::class,
            UnrepresentableIdempotencyScope::class,
        ] as $class) {
            self::assertTrue(class_exists($class), $class . ' must exist before hashing behavior can pass.');
        }
    }
}

final class IdempotencyScopeObject
{
    public function __construct(public string $label) {}
}
