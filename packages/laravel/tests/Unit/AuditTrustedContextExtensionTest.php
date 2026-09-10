<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\DatabaseAuditEventStore;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class AuditTrustedContextExtensionTest extends TestCase
{
    private const string TABLE = 'surfacerelay_audit_events';

    public function test_factory_projects_core_and_extension_manifest_without_trusted_material(): void
    {
        $event = $this->event();

        if (count($event->trustedContextManifest) !== 2) {
            self::fail('Audit manifest must include both the core tenant entry and trusted extension entry.');
        }

        $core = $event->trustedContextManifest[0];
        self::assertSame(ContextRequirement::Tenant, $core->requirement);
        self::assertNull($core->extension);
        self::assertSame('tenant.provider', $core->provider);

        $extension = $event->trustedContextManifest[1];
        self::assertNull($extension->requirement);
        self::assertSame('filament/active_filters', $extension->extension);
        self::assertSame('filament.active_filters', $extension->provider);

        $manifest = json_encode(array_map(
            static fn ($entry): array => array_filter([
                'requirement' => $entry->requirement?->value,
                'extension' => $entry->extension,
                'provider' => $entry->provider,
            ], static fn (mixed $value): bool => $value !== null),
            $event->trustedContextManifest,
        ), JSON_THROW_ON_ERROR);

        foreach ([
            'SECRET_TENANT_VALUE',
            'SECRET_TENANT_REFERENCE',
            'SECRET_TENANT_SCOPE',
            'SECRET_FILTER_VALUE',
            'SECRET_FILTER_REFERENCE',
            'SECRET_FILTER_SCOPE',
            'SECRET_METADATA_FILTER',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $manifest);
        }
    }

    public function test_store_serializes_disjoint_extension_manifest_without_filter_or_scope_material(): void
    {
        $connection = $this->connection();
        $event = $this->event();

        (new DatabaseAuditEventStore($connection))->append($event);

        $row = $connection->table(self::TABLE)->where('event_id', $event->eventId)->first();
        self::assertNotNull($row);

        $manifest = json_decode($row->trusted_context_manifest, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([
            ['requirement' => 'tenant', 'provider' => 'tenant.provider'],
            ['extension' => 'filament/active_filters', 'provider' => 'filament.active_filters'],
        ], $manifest);

        $persisted = json_encode($manifest, JSON_THROW_ON_ERROR);
        foreach ([
            'SECRET_TENANT_VALUE',
            'SECRET_TENANT_REFERENCE',
            'SECRET_TENANT_SCOPE',
            'SECRET_FILTER_VALUE',
            'SECRET_FILTER_REFERENCE',
            'SECRET_FILTER_SCOPE',
            'SECRET_METADATA_FILTER',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $persisted);
        }
    }

    private function event(): \SurfaceRelay\Laravel\Audit\AuditEvent
    {
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-filter-audit',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::Tenant,
                    'SECRET_TENANT_VALUE',
                    new ContextProvenance('tenant.provider', 'SECRET_TENANT_REFERENCE'),
                    confirmationScopeKey: 'SECRET_TENANT_SCOPE',
                ),
            ],
            metadata: [
                'filament/active_filters' => ['status' => ['value' => 'SECRET_METADATA_FILTER']],
            ],
            trustedExtensions: [
                new TrustedContextExtension(
                    'filament/active_filters',
                    ['status' => ['value' => 'SECRET_FILTER_VALUE']],
                    new ContextProvenance('filament.active_filters', 'SECRET_FILTER_REFERENCE'),
                    scopeKey: 'SECRET_FILTER_SCOPE',
                ),
            ],
        );

        return $this->factory()->create(ActionPipelineOutcome::completed(
            new ActionPipelineState(
                definition: $this->definition(),
                input: ['marker' => 'ordinary-input'],
                context: $context,
                hasOutput: true,
                output: ['ok' => true],
            ),
        ));
    }

    private function factory(): AuditEventFactory
    {
        $clock = new class implements AuditClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-10 13:20:00.123456', new DateTimeZone('UTC'));
            }
        };

        return new AuditEventFactory($clock);
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.filtered.audit',
            version: 1,
            title: 'Audit filtered orders',
            description: 'Exercises trusted active-filter audit projection.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::Tenant],
            outputSchema: ['type' => 'object'],
        );
    }

    private function connection(): ConnectionInterface
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $connection = $capsule->getConnection();

        $connection->getSchemaBuilder()->create(self::TABLE, static function (Blueprint $table): void {
            $table->char('event_id', 32)->primary();
            $table->dateTime('recorded_at', precision: 6)->index();
            $table->longText('correlation_id');
            $table->char('correlation_hash', 64)->index();
            $table->longText('surface');
            $table->string('action_id', 160);
            $table->unsignedInteger('action_version');
            $table->string('action_scope', 32);
            $table->string('action_effect', 32);
            $table->string('action_risk', 32);
            $table->string('idempotency_policy', 32);
            $table->string('output_sensitivity', 32);
            $table->string('output_content_trust', 64);
            $table->string('outcome_kind', 16);
            $table->string('halted_at', 32)->nullable();
            $table->longText('halt_code')->nullable();
            $table->char('halt_code_hash', 64)->nullable();
            $table->boolean('human_confirmation_present');
            $table->json('trusted_context_manifest');
            $table->index(['action_id', 'action_version', 'recorded_at'], 'sr_audit_action_recorded_idx');
            $table->index(['outcome_kind', 'halt_code_hash', 'recorded_at'], 'sr_audit_outcome_halt_recorded_idx');
        });

        return $connection;
    }
}
