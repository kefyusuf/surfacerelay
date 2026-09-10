<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Context\FilamentActiveFilterContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\FilamentInvocationContextFactory;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyPreflightKind;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreClaimResult;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentActiveFilterTrustControlsIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['db']->connection()->getSchemaBuilder()->create(
            'filament_test_records',
            static function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('name');
            },
        );
    }

    public function test_applied_filters_bind_confirmation_and_idempotency_while_pending_and_metadata_state_do_not(): void
    {
        $page = $this->page();
        $this->applyFilters($page, status: true, priority: false);

        $stateA = $this->state($page, 'corr-A');
        $stateMetadataSpoof = $this->state($page, 'corr-spoof', [
            'filament/active_filters' => [
                'status' => ['isActive' => false],
                'priority' => ['isActive' => true],
            ],
        ]);

        $confirmationHasher = new ConfirmationScopeHasher();
        $idempotencyHasher = new IdempotencyIntentHasher();
        $confirmationA = $confirmationHasher->fingerprint($stateA);
        $intentA = $idempotencyHasher->fingerprint($stateA);

        self::assertSame($confirmationA, $confirmationHasher->fingerprint($stateMetadataSpoof));
        self::assertSame($intentA, $idempotencyHasher->fingerprint($stateMetadataSpoof));

        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => false],
            'priority' => ['isActive' => true],
        ]);

        $statePendingB = $this->state($page, 'corr-pending-B');
        self::assertSame($confirmationA, $confirmationHasher->fingerprint($statePendingB));
        self::assertSame($intentA, $idempotencyHasher->fingerprint($statePendingB));

        $page->applyTableFilters();
        $stateB = $this->state($page, 'corr-B');
        $confirmationB = $confirmationHasher->fingerprint($stateB);
        $intentB = $idempotencyHasher->fingerprint($stateB);

        self::assertNotSame($confirmationA, $confirmationB);
        self::assertNotSame($intentA, $intentB);

        $extensionA = $stateA->context->requireTrustedExtension(
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );
        $extensionB = $stateB->context->requireTrustedExtension(
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );
        self::assertNotSame($extensionA->scopeKey, $extensionB->scopeKey);
    }

    public function test_filter_a_receipt_cannot_authorize_applied_filter_b_and_wrong_scope_does_not_spend_it(): void
    {
        $page = $this->page();
        $this->applyFilters($page, status: true, priority: false);
        $scopeA = (new ConfirmationScopeHasher())->fingerprint($this->state($page, 'corr-A'));

        $service = new ConfirmationService(
            new ActiveFilterMemoryConfirmationStore(),
            new ActiveFilterFixedConfirmationClock(),
            new ActiveFilterFixedConfirmationTokenGenerator(),
        );
        $challenge = $service->issueChallenge($scopeA, 'Approve filtered order action');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        $this->applyFilters($page, status: false, priority: true);
        $scopeB = (new ConfirmationScopeHasher())->fingerprint($this->state($page, 'corr-B'));

        self::assertNotSame($scopeA, $scopeB);
        self::assertFalse($service->consumeReceipt($receipt, $scopeB));
        self::assertTrue(
            $service->consumeReceipt($receipt, $scopeA),
            'A wrong applied-filter scope must not spend the valid state-A receipt.',
        );
    }

    public function test_same_idempotency_key_replays_same_filter_intent_but_conflicts_after_applied_filter_change(): void
    {
        $page = $this->page();
        $this->applyFilters($page, status: true, priority: false);
        $intentA = (new IdempotencyIntentHasher())->fingerprint($this->state($page, 'corr-A'));

        $codec = new IdempotencyReplayCodec();
        $keyHash = hash('sha256', 'active-filter-idempotency-key');
        $store = new ActiveFilterMemoryIdempotencyStore();
        $store->seed(new IdempotencyRecord(
            keyHash: $keyHash,
            intentFingerprint: $intentA,
            state: IdempotencyRecordState::Completed,
            outputPayload: $codec->encode(['source' => 'filter-A']),
            createdAt: 1_800_000_000,
            expiresAt: 1_800_001_000,
        ));
        $service = new IdempotencyService(
            $store,
            new ActiveFilterFixedIdempotencyClock(),
            $codec,
        );

        $same = $service->preflight($keyHash, $intentA);
        self::assertSame(IdempotencyPreflightKind::Replay, $same->kind);
        self::assertSame(['source' => 'filter-A'], $same->output);

        $this->applyFilters($page, status: false, priority: true);
        $intentB = (new IdempotencyIntentHasher())->fingerprint($this->state($page, 'corr-B'));
        self::assertNotSame($intentA, $intentB);

        $changed = $service->preflight($keyHash, $intentB);
        self::assertSame(IdempotencyPreflightKind::Conflict, $changed->kind);
        self::assertNull($changed->output);
    }

    public function test_audit_records_only_fixed_extension_identity_and_provider(): void
    {
        $page = $this->page();
        $this->applyFilters($page, status: true, priority: false);
        $state = $this->state($page, 'corr-audit', [
            'filament/active_filters' => ['SECRET_METADATA_FILTER' => true],
        ]);
        $extension = $state->context->requireTrustedExtension(
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );
        self::assertNotNull($extension->scopeKey);

        $event = (new AuditEventFactory(new ActiveFilterFixedAuditClock()))
            ->create(ActionPipelineOutcome::completed($state));

        self::assertCount(1, $event->trustedContextManifest);
        $entry = $event->trustedContextManifest[0];
        self::assertNull($entry->requirement);
        self::assertSame('filament/active_filters', $entry->extension);
        self::assertSame('filament.active_filters', $entry->provider);

        $manifest = json_encode([
            'extension' => $entry->extension,
            'provider' => $entry->provider,
        ], JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('status', $manifest);
        self::assertStringNotContainsString('priority', $manifest);
        self::assertStringNotContainsString('isActive', $manifest);
        self::assertStringNotContainsString('SECRET_METADATA_FILTER', $manifest);
        self::assertStringNotContainsString($extension->scopeKey, $manifest);
    }

    private function page(): TestTablePage
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();

        return $page;
    }

    private function applyFilters(TestTablePage $page, bool $status, bool $priority): void
    {
        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => $status],
            'priority' => ['isActive' => $priority],
        ]);
        $page->applyTableFilters();
    }

    /** @param array<string, mixed> $metadata */
    private function state(
        TestTablePage $page,
        string $correlationId,
        array $metadata = [],
    ): ActionPipelineState {
        $context = $this->factory()->forPage(
            page: $page,
            surface: 'filament',
            correlationId: $correlationId,
            idempotencyKey: 'same-caller-key',
            metadata: $metadata,
            contextExposure: FilamentContextExposure::activeFilters(),
        );

        return new ActionPipelineState(
            definition: $this->definition(),
            input: ['reason' => 'review-filtered-orders'],
            context: $context,
            bindingId: 'livewire-binding-filter-page',
        );
    }

    private function factory(): FilamentInvocationContextFactory
    {
        return new FilamentInvocationContextFactory(new TrustedContextComposer(
            new NullActiveFilterTrustActorResolver(),
            new NullActiveFilterTrustTenantResolver(),
        ));
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.review_filtered',
            version: 1,
            title: 'Review filtered orders',
            description: 'Exercises confirmation and idempotency against trusted active filters.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['reason' => ['type' => 'string']],
                'required' => ['reason'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}

final class NullActiveFilterTrustActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullActiveFilterTrustTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class ActiveFilterMemoryConfirmationStore implements ConfirmationStore
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
        if ($record === null
            || $record->state !== ConfirmationRecordState::Pending
            || $now >= $record->challengeExpiresAt) {
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
        if ($record === null
            || $record->state !== ConfirmationRecordState::Approved
            || $record->scopeFingerprint !== $expectedScopeFingerprint
            || $record->receiptExpiresAt === null
            || $now >= $record->receiptExpiresAt) {
            return false;
        }

        unset($this->records[$tokenHash]);
        return true;
    }
}

final class ActiveFilterFixedConfirmationClock implements ConfirmationClock
{
    public function now(): int
    {
        return 1_800_000_000;
    }
}

final class ActiveFilterFixedConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    public function generate(): string
    {
        return str_repeat('F', 43);
    }
}

final class ActiveFilterMemoryIdempotencyStore implements IdempotencyStore
{
    /** @var array<string, IdempotencyRecord> */
    private array $records = [];

    public function seed(IdempotencyRecord $record): void
    {
        $this->records[$record->keyHash] = $record;
    }

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
        $record = $this->records[$keyHash];
        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $keyHash,
            intentFingerprint: $intentFingerprint,
            state: IdempotencyRecordState::Completed,
            outputPayload: $outputPayload,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }

    public function markIndeterminate(string $keyHash, string $intentFingerprint): void
    {
        $record = $this->records[$keyHash];
        $this->records[$keyHash] = new IdempotencyRecord(
            keyHash: $keyHash,
            intentFingerprint: $intentFingerprint,
            state: IdempotencyRecordState::Indeterminate,
            outputPayload: null,
            createdAt: $record->createdAt,
            expiresAt: $record->expiresAt,
        );
    }
}

final class ActiveFilterFixedIdempotencyClock implements IdempotencyClock
{
    public function now(): int
    {
        return 1_800_000_100;
    }
}

final class ActiveFilterFixedAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            '2026-09-10 13:30:00.123456',
            new DateTimeZone('UTC'),
        );
    }
}
