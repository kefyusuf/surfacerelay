<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\AuditTrustedContextEntry;
use SurfaceRelay\Laravel\Confirmation\ConfirmationClock;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecord;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentCurrentSelectionTrustControlsIntegrationTest extends TestCase
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

        TestRecord::query()->create(['id' => 1, 'name' => 'record-A']);
        TestRecord::query()->create(['id' => 2, 'name' => 'record-B']);
        TestRecord::query()->create(['id' => 3, 'name' => 'record-C']);
    }

    public function test_confirmation_and_idempotency_are_order_independent_but_bind_exact_selection_set(): void
    {
        $stateAB = $this->stateForSelection([1, 2]);
        $stateBA = $this->stateForSelection([2, 1]);
        $stateAC = $this->stateForSelection([1, 3]);

        $scopeAB = $stateAB->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;
        $scopeBA = $stateBA->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;
        $scopeAC = $stateAC->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;

        self::assertNotNull($scopeAB);
        self::assertSame($scopeAB, $scopeBA);
        self::assertNotSame($scopeAB, $scopeAC);

        $confirmation = new ConfirmationScopeHasher();
        self::assertSame(
            $confirmation->fingerprint($stateAB),
            $confirmation->fingerprint($stateBA),
        );
        self::assertNotSame(
            $confirmation->fingerprint($stateAB),
            $confirmation->fingerprint($stateAC),
        );

        $idempotency = new IdempotencyIntentHasher();
        self::assertSame(
            $idempotency->fingerprint($stateAB),
            $idempotency->fingerprint($stateBA),
        );
        self::assertNotSame(
            $idempotency->fingerprint($stateAB),
            $idempotency->fingerprint($stateAC),
        );
    }

    public function test_tenant_is_independent_from_selection_identity_but_participates_in_full_scope(): void
    {
        $stateA = $this->stateForSelection([1, 2], 'tenant-a');
        $stateB = $this->stateForSelection([2, 1], 'tenant-b');

        $selectionScopeA = $stateA->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;
        $selectionScopeB = $stateB->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;

        self::assertNotNull($selectionScopeA);
        self::assertSame(
            $selectionScopeA,
            $selectionScopeB,
            'Selection identity must not absorb tenant identity.',
        );
        self::assertNotSame(
            (new ConfirmationScopeHasher())->fingerprint($stateA),
            (new ConfirmationScopeHasher())->fingerprint($stateB),
        );
        self::assertNotSame(
            (new IdempotencyIntentHasher())->fingerprint($stateA),
            (new IdempotencyIntentHasher())->fingerprint($stateB),
        );
    }

    public function test_receipt_for_selection_ab_cannot_authorize_ac_and_mismatch_does_not_spend_it(): void
    {
        $scopeAB = (new ConfirmationScopeHasher())->fingerprint(
            $this->stateForSelection([1, 2]),
        );
        $scopeAC = (new ConfirmationScopeHasher())->fingerprint(
            $this->stateForSelection([1, 3]),
        );

        $service = new ConfirmationService(
            new SelectionMemoryConfirmationStore(),
            new SelectionFixedConfirmationClock(),
            new SelectionFixedConfirmationTokenGenerator(),
        );

        $challenge = $service->issueChallenge($scopeAB, 'Approve selected orders');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        self::assertFalse($service->consumeReceipt($receipt, $scopeAC));
        self::assertTrue(
            $service->consumeReceipt($receipt, $scopeAB),
            'Wrong-selection mismatch must not spend the valid AB receipt.',
        );
    }

    public function test_structured_audit_records_only_selection_manifest_not_selected_record_material(): void
    {
        TestRecord::query()->whereKey(1)->update(['name' => 'SECRET-SELECTION-ATTRIBUTE-A']);
        TestRecord::query()->whereKey(2)->update(['name' => 'SECRET-SELECTION-ATTRIBUTE-B']);

        $state = $this->stateForSelection([1, 2], 'TENANT-SELECTION-SECRET');
        $selectionScopeKey = $state->context
            ->require(ContextRequirement::CurrentSelection)
            ->confirmationScopeKey;
        self::assertNotNull($selectionScopeKey);

        $event = (new AuditEventFactory(new SelectionFixedAuditClock()))
            ->create(ActionPipelineOutcome::completed($state));

        $manifest = array_map(
            static fn (AuditTrustedContextEntry $entry): array => [
                'requirement' => $entry->requirement->value,
                'provider' => $entry->provider,
            ],
            $event->trustedContextManifest,
        );

        self::assertContains(
            ['requirement' => 'current_selection', 'provider' => 'filament.current_selection'],
            $manifest,
        );

        $projection = [
            'eventId' => $event->eventId,
            'recordedAt' => $event->recordedAt->format('Y-m-d H:i:s.u T'),
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
            'trustedContextManifest' => $manifest,
        ];
        $json = json_encode($projection, JSON_THROW_ON_ERROR);

        self::assertStringNotContainsString('SECRET-SELECTION-ATTRIBUTE-A', $json);
        self::assertStringNotContainsString('SECRET-SELECTION-ATTRIBUTE-B', $json);
        self::assertStringNotContainsString('TENANT-SELECTION-SECRET', $json);
        self::assertStringNotContainsString($selectionScopeKey, $json);
        self::assertStringNotContainsString(TestRecord::class, $json);
    }

    /** @param list<int> $selectedIds */
    private function stateForSelection(array $selectedIds, string $tenant = 'tenant-a'): ActionPipelineState
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();
        $page->selectedTableRecords = $selectedIds;

        $selection = (new FilamentCurrentSelectionResolver($page))->resolve();
        self::assertNotNull($selection);

        return new ActionPipelineState(
            definition: $this->definition(),
            input: ['reason' => 'customer-request'],
            context: new InvocationContext(
                surface: 'filament',
                correlationId: 'corr-filament-selection',
                trustedContext: [
                    new TrustedContextEntry(
                        ContextRequirement::Tenant,
                        $tenant,
                        new ContextProvenance('test.tenant'),
                        'tenant-scope:' . $tenant,
                    ),
                    new TrustedContextEntry(
                        ContextRequirement::CurrentSelection,
                        $selection->value,
                        $selection->provenance,
                        $selection->confirmationScopeKey,
                    ),
                ],
            ),
            bindingId: 'livewire-binding-selection-page',
        );
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund_selected',
            version: 1,
            title: 'Refund selected orders',
            description: 'Refund the exact trusted Filament current selection.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string'],
                ],
                'required' => ['reason'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::Tenant,
                ContextRequirement::CurrentSelection,
                ContextRequirement::HumanConfirmation,
            ],
        );
    }
}

final class SelectionMemoryConfirmationStore implements ConfirmationStore
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

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if (
            $record === null
            || $record->state !== ConfirmationRecordState::Approved
            || $record->scopeFingerprint !== $expectedScopeFingerprint
            || $record->receiptExpiresAt === null
            || $now >= $record->receiptExpiresAt
        ) {
            return false;
        }

        unset($this->records[$tokenHash]);

        return true;
    }
}

final class SelectionFixedConfirmationClock implements ConfirmationClock
{
    public function now(): int
    {
        return 1_800_000_000;
    }
}

final class SelectionFixedConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    public function generate(): string
    {
        return str_repeat('S', 43);
    }
}

final class SelectionFixedAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            '2026-09-10 00:00:00.123456',
            new DateTimeZone('UTC'),
        );
    }
}
