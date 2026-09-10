<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
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
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class FilamentRecordTrustControlsIntegrationTest extends TestCase
{
    public function test_confirmation_and_idempotency_bind_exact_record_identity(): void
    {
        $recordA = $this->persistedRecord('RECORD-A');
        $recordB = $this->persistedRecord('RECORD-B');
        $recordASecondInstance = $this->persistedRecord('RECORD-A', 'different-attributes');

        $confirmation = new ConfirmationScopeHasher();
        $idempotency = new IdempotencyIntentHasher();

        self::assertNotSame(
            $confirmation->fingerprint($this->stateForRecord($recordA)),
            $confirmation->fingerprint($this->stateForRecord($recordB)),
        );
        self::assertSame(
            $confirmation->fingerprint($this->stateForRecord($recordA)),
            $confirmation->fingerprint($this->stateForRecord($recordASecondInstance)),
        );
        self::assertNotSame(
            $idempotency->fingerprint($this->stateForRecord($recordA)),
            $idempotency->fingerprint($this->stateForRecord($recordB)),
        );
        self::assertSame(
            $idempotency->fingerprint($this->stateForRecord($recordA)),
            $idempotency->fingerprint($this->stateForRecord($recordASecondInstance)),
        );
    }

    public function test_tenant_is_independent_from_record_identity_but_participates_in_full_scope(): void
    {
        $record = $this->persistedRecord('RECORD-SHARED');
        $stateA = $this->stateForRecord($record, 'tenant-a');
        $stateB = $this->stateForRecord($record, 'tenant-b');

        $recordScopeA = $stateA->context
            ->require(ContextRequirement::CurrentRecord)
            ->confirmationScopeKey;
        $recordScopeB = $stateB->context
            ->require(ContextRequirement::CurrentRecord)
            ->confirmationScopeKey;

        self::assertNotNull($recordScopeA);
        self::assertSame($recordScopeA, $recordScopeB,
            'Filament record identity must not secretly absorb tenant identity.');
        self::assertNotSame(
            (new ConfirmationScopeHasher())->fingerprint($stateA),
            (new ConfirmationScopeHasher())->fingerprint($stateB),
        );
        self::assertNotSame(
            (new IdempotencyIntentHasher())->fingerprint($stateA),
            (new IdempotencyIntentHasher())->fingerprint($stateB),
        );
    }

    public function test_receipt_for_record_a_cannot_authorize_record_b_and_mismatch_does_not_spend_it(): void
    {
        $scopeA = (new ConfirmationScopeHasher())->fingerprint(
            $this->stateForRecord($this->persistedRecord('RECORD-A')),
        );
        $scopeB = (new ConfirmationScopeHasher())->fingerprint(
            $this->stateForRecord($this->persistedRecord('RECORD-B')),
        );

        $service = new ConfirmationService(
            new FilamentMemoryConfirmationStore(),
            new FilamentFixedConfirmationClock(),
            new FilamentFixedConfirmationTokenGenerator(),
        );

        $challenge = $service->issueChallenge($scopeA, 'Approve refund');
        $receipt = $service->approveChallenge($challenge->challengeId);
        self::assertNotNull($receipt);

        self::assertFalse($service->consumeReceipt($receipt, $scopeB));
        self::assertTrue($service->consumeReceipt($receipt, $scopeA),
            'Wrong-record attempt must not spend the exact original receipt.');
    }

    public function test_structured_audit_records_only_current_record_manifest_not_record_material(): void
    {
        $recordKey = 'RECORD-A-SECRET-KEY';
        $attribute = 'RECORD-A-SECRET-ATTRIBUTE';
        $tenant = 'TENANT-SECRET-VALUE';
        $record = $this->persistedRecord($recordKey, $attribute);
        $state = $this->stateForRecord($record, $tenant);
        $recordScopeKey = $state->context
            ->require(ContextRequirement::CurrentRecord)
            ->confirmationScopeKey;
        self::assertNotNull($recordScopeKey);

        $event = (new AuditEventFactory(new FilamentFixedAuditClock()))
            ->create(ActionPipelineOutcome::completed($state));

        $manifest = array_map(
            static fn (AuditTrustedContextEntry $entry): array => [
                'requirement' => $entry->requirement->value,
                'provider' => $entry->provider,
            ],
            $event->trustedContextManifest,
        );

        self::assertContains(
            ['requirement' => 'current_record', 'provider' => 'filament.current_record'],
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

        self::assertStringNotContainsString($recordKey, $json);
        self::assertStringNotContainsString($attribute, $json);
        self::assertStringNotContainsString($tenant, $json);
        self::assertStringNotContainsString($recordScopeKey, $json);
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund_current',
            version: 1,
            title: 'Refund current order',
            description: 'Refund the exact current Filament order record.',
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
                ContextRequirement::CurrentRecord,
                ContextRequirement::HumanConfirmation,
            ],
        );
    }

    private function stateForRecord(Model $record, string $tenant = 'tenant-a'): ActionPipelineState
    {
        $page = new TrustControlRecordPage();
        $page->resolvedRecord = $record;

        $recordValue = (new FilamentRecordContextResolver($page))->resolve();
        self::assertNotNull($recordValue);

        return new ActionPipelineState(
            definition: $this->definition(),
            input: ['reason' => 'customer-request'],
            context: new InvocationContext(
                surface: 'filament',
                correlationId: 'corr-filament-record',
                trustedContext: [
                    new TrustedContextEntry(
                        ContextRequirement::Tenant,
                        $tenant,
                        new ContextProvenance('test.tenant'),
                        'tenant-scope:' . $tenant,
                    ),
                    new TrustedContextEntry(
                        ContextRequirement::CurrentRecord,
                        $recordValue->value,
                        $recordValue->provenance,
                        $recordValue->confirmationScopeKey,
                    ),
                ],
            ),
            bindingId: 'livewire-binding-record-page',
        );
    }

    private function persistedRecord(string|int $key, string $attribute = 'record-attribute'): TrustControlRecord
    {
        $record = new TrustControlRecord();
        if (is_string($key)) {
            $record->setKeyType('string');
        }
        $record->setRawAttributes([
            'id' => $key,
            'name' => $attribute,
        ]);
        $record->exists = true;

        return $record;
    }
}

final class TrustControlRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class TrustControlResource extends Resource
{
    protected static ?string $model = TrustControlRecord::class;

    public static function getPages(): array
    {
        return [];
    }
}

final class TrustControlRecordPage extends Page
{
    protected static string $resource = TrustControlResource::class;

    protected string $view = 'trust-control-record';

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}

final class FilamentMemoryConfirmationStore implements ConfirmationStore
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

final class FilamentFixedConfirmationClock implements ConfirmationClock
{
    public function now(): int
    {
        return 1_800_000_000;
    }
}

final class FilamentFixedConfirmationTokenGenerator implements ConfirmationTokenGenerator
{
    public function generate(): string
    {
        return str_repeat('A', 43);
    }
}

final class FilamentFixedAuditClock implements AuditClock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(
            '2026-09-10 00:00:00.123456',
            new DateTimeZone('UTC'),
        );
    }
}
