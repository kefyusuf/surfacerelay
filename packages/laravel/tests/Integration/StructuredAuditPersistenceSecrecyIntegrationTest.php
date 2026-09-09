<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\DatabaseAuditEventStore;
use SurfaceRelay\Laravel\Audit\StructuredActionPipelineAuditor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class StructuredAuditPersistenceSecrecyIntegrationTest extends TestCase
{
    private const string TABLE = 'surfacerelay_audit_events';

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_durable_rows_and_schema_exclude_every_reachable_forbidden_payload_and_authority_source(): void
    {
        $connection = StructuredAuditPersistenceSecrecyHarness::connection();
        StructuredAuditPersistenceSecrecyHarness::installMigration($connection);

        try {
            $definition = StructuredAuditPersistenceSecrecyHarness::definition();
            $context = StructuredAuditPersistenceSecrecyHarness::context();
            $auditor = new StructuredActionPipelineAuditor(
                new AuditEventFactory(new StructuredAuditPersistenceClock()),
                new DatabaseAuditEventStore($connection),
            );

            $completedCall = new ActionCall(
                $definition->id,
                $definition->version,
                ['raw' => 'SECRET_RAW_INPUT'],
                $context,
                bindingId: 'SECRET_BINDING_ID',
                confirmationReceipt: 'SECRET_CONFIRMATION_RECEIPT',
            );
            $completedState = new ActionPipelineState(
                definition: $definition,
                input: ['validated' => 'SECRET_VALIDATED_INPUT'],
                context: $context,
                hasOutput: true,
                output: ['normal' => 'SECRET_NORMAL_OUTPUT'],
                bindingId: 'SECRET_BINDING_ID',
                confirmationReceipt: 'SECRET_CONFIRMATION_RECEIPT',
                idempotencyPlan: IdempotencyExecutionPlan::replay(
                    'SECRET_IDEMPOTENCY_LOOKUP_HASH',
                    'SECRET_INTENT_FINGERPRINT',
                    ['payload' => 'SECRET_REPLAY_PAYLOAD'],
                ),
            );
            $auditor->record($completedCall, ActionPipelineOutcome::completed($completedState));

            $haltedCall = new ActionCall(
                $definition->id,
                $definition->version,
                ['raw' => 'SECRET_RAW_INPUT_SECOND'],
                new InvocationContext(
                    surface: $context->surface,
                    correlationId: 'corr-secrecy-halted',
                    trustedContext: $context->allTrusted(),
                    idempotencyKey: 'SECRET_RAW_IDEMPOTENCY_KEY_SECOND',
                    metadata: ['secret' => 'SECRET_METADATA_SECOND'],
                ),
                bindingId: 'SECRET_BINDING_ID_SECOND',
                confirmationReceipt: 'SECRET_CONFIRMATION_RECEIPT_SECOND',
            );
            $haltedState = new ActionPipelineState(
                definition: $definition,
                input: ['validated' => 'SECRET_VALIDATED_INPUT_SECOND'],
                context: $haltedCall->context,
                hasOutput: true,
                output: ['released' => 'SECRET_RELEASED_OUTPUT'],
                bindingId: $haltedCall->bindingId,
                confirmationReceipt: $haltedCall->confirmationReceipt,
                idempotencyPlan: IdempotencyExecutionPlan::replay(
                    'SECRET_IDEMPOTENCY_LOOKUP_HASH_SECOND',
                    'SECRET_INTENT_FINGERPRINT_SECOND',
                    ['payload' => 'SECRET_REPLAY_PAYLOAD_SECOND'],
                ),
            );
            $auditor->record($haltedCall, ActionPipelineOutcome::halted(
                $haltedState,
                ActionPipelineStage::OutputPolicy,
                new ActionPipelineHalt(
                    'test_halt',
                    ['secret' => 'SECRET_HALT_DETAILS'],
                    new ConfirmationChallenge(
                        'SECRET_CHALLENGE_ID',
                        'SECRET_CHALLENGE_SUMMARY',
                        '2026-09-09T18:30:00Z',
                    ),
                ),
            ));

            $rows = $connection->table(self::TABLE)->orderBy('recorded_at')->get();
            self::assertCount(2, $rows);
            $persisted = json_encode(
                array_map(static fn (object $row): array => (array) $row, $rows->all()),
                JSON_THROW_ON_ERROR,
            );

            foreach (StructuredAuditPersistenceSecrecyHarness::forbiddenMarkers() as $marker) {
                self::assertStringNotContainsString($marker, $persisted, 'Forbidden marker leaked: ' . $marker);
            }

            foreach ([
                'actor.provider',
                'tenant.provider',
                'record.provider',
                'selection.provider',
                'session.provider',
                'surfacerelay.confirmation',
            ] as $allowedProvider) {
                self::assertStringContainsString($allowedProvider, $persisted);
            }

            foreach ($rows as $row) {
                $manifest = json_decode($row->trusted_context_manifest, true, flags: JSON_THROW_ON_ERROR);
                self::assertNotEmpty($manifest);
                foreach ($manifest as $entry) {
                    self::assertSame(['requirement', 'provider'], array_keys($entry));
                    self::assertIsString($entry['requirement']);
                    self::assertIsString($entry['provider']);
                }
            }

            $columns = $connection->getSchemaBuilder()->getColumnListing(self::TABLE);
            foreach ([
                'input',
                'validated_input',
                'output',
                'metadata',
                'idempotency_key',
                'idempotency_lookup_hash',
                'intent_fingerprint',
                'replay_payload',
                'binding_id',
                'confirmation_receipt',
                'challenge_id',
                'actor_id',
                'tenant_id',
                'current_record',
                'current_selection',
                'browser_session',
                'provenance_reference',
                'scope_key',
                'halt_details',
            ] as $forbiddenColumn) {
                self::assertNotContains($forbiddenColumn, $columns);
            }
        } finally {
            StructuredAuditPersistenceSecrecyHarness::dropMigration($connection);
        }
    }
}

final class StructuredAuditPersistenceSecrecyHarness
{
    public static function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'audit.secrecy',
            version: 4,
            title: 'Audit secrecy',
            description: 'Proves durable audit payload minimization.',
            inputSchema: [
                'type' => 'object',
                'secretSchema' => 'SECRET_SCHEMA',
            ],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::ContainsUntrustedContent,
            contextRequirements: [],
            outputSchema: [
                'type' => 'object',
                'secretOutputSchema' => 'SECRET_OUTPUT_SCHEMA',
            ],
            extensions: [
                'example/secret' => 'SECRET_EXTENSION',
            ],
        );
    }

    public static function context(): InvocationContext
    {
        return new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-secrecy-completed',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::AuthenticatedActor,
                    'SECRET_TRUSTED_ACTOR',
                    new ContextProvenance('actor.provider', 'SECRET_ACTOR_REFERENCE'),
                    confirmationScopeKey: 'SECRET_ACTOR_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::Tenant,
                    'SECRET_TRUSTED_TENANT',
                    new ContextProvenance('tenant.provider', 'SECRET_TENANT_REFERENCE'),
                    confirmationScopeKey: 'SECRET_TENANT_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::CurrentRecord,
                    'SECRET_TRUSTED_RECORD',
                    new ContextProvenance('record.provider', 'SECRET_RECORD_REFERENCE'),
                    confirmationScopeKey: 'SECRET_RECORD_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::CurrentSelection,
                    ['SECRET_TRUSTED_SELECTION'],
                    new ContextProvenance('selection.provider', 'SECRET_SELECTION_REFERENCE'),
                    confirmationScopeKey: 'SECRET_SELECTION_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::BrowserSession,
                    'SECRET_BROWSER_SESSION',
                    new ContextProvenance('session.provider', 'SECRET_SESSION_REFERENCE'),
                    confirmationScopeKey: 'SECRET_SESSION_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::HumanConfirmation,
                    true,
                    new ContextProvenance('surfacerelay.confirmation', 'SECRET_CONFIRMATION_REFERENCE'),
                    confirmationScopeKey: 'SECRET_CONFIRMATION_SCOPE',
                ),
            ],
            idempotencyKey: 'SECRET_RAW_IDEMPOTENCY_KEY',
            metadata: ['secret' => 'SECRET_METADATA'],
        );
    }

    /** @return list<string> */
    public static function forbiddenMarkers(): array
    {
        return [
            'SECRET_RAW_INPUT',
            'SECRET_RAW_INPUT_SECOND',
            'SECRET_VALIDATED_INPUT',
            'SECRET_VALIDATED_INPUT_SECOND',
            'SECRET_NORMAL_OUTPUT',
            'SECRET_RELEASED_OUTPUT',
            'SECRET_METADATA',
            'SECRET_METADATA_SECOND',
            'SECRET_RAW_IDEMPOTENCY_KEY',
            'SECRET_RAW_IDEMPOTENCY_KEY_SECOND',
            'SECRET_IDEMPOTENCY_LOOKUP_HASH',
            'SECRET_IDEMPOTENCY_LOOKUP_HASH_SECOND',
            'SECRET_INTENT_FINGERPRINT',
            'SECRET_INTENT_FINGERPRINT_SECOND',
            'SECRET_REPLAY_PAYLOAD',
            'SECRET_REPLAY_PAYLOAD_SECOND',
            'SECRET_BINDING_ID',
            'SECRET_BINDING_ID_SECOND',
            'SECRET_CONFIRMATION_RECEIPT',
            'SECRET_CONFIRMATION_RECEIPT_SECOND',
            'SECRET_TRUSTED_ACTOR',
            'SECRET_TRUSTED_TENANT',
            'SECRET_TRUSTED_RECORD',
            'SECRET_TRUSTED_SELECTION',
            'SECRET_BROWSER_SESSION',
            'SECRET_ACTOR_REFERENCE',
            'SECRET_TENANT_REFERENCE',
            'SECRET_RECORD_REFERENCE',
            'SECRET_SELECTION_REFERENCE',
            'SECRET_SESSION_REFERENCE',
            'SECRET_CONFIRMATION_REFERENCE',
            'SECRET_ACTOR_SCOPE',
            'SECRET_TENANT_SCOPE',
            'SECRET_RECORD_SCOPE',
            'SECRET_SELECTION_SCOPE',
            'SECRET_SESSION_SCOPE',
            'SECRET_CONFIRMATION_SCOPE',
            'SECRET_HALT_DETAILS',
            'SECRET_CHALLENGE_ID',
            'SECRET_CHALLENGE_SUMMARY',
            'SECRET_SCHEMA',
            'SECRET_OUTPUT_SCHEMA',
            'SECRET_EXTENSION',
        ];
    }

    public static function connection(): ConnectionInterface
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        return $capsule->getConnection();
    }

    public static function installMigration(ConnectionInterface $connection): void
    {
        $container = new Container();
        $container->instance('db.schema', $connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);
        self::migration()->up();
    }

    public static function dropMigration(ConnectionInterface $connection): void
    {
        $container = new Container();
        $container->instance('db.schema', $connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);
        self::migration()->down();
    }

    private static function migration(): object
    {
        $path = dirname(__DIR__, 2)
            . '/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php';
        if (!is_file($path)) {
            throw new \RuntimeException('T-404 audit migration is missing.');
        }
        return require $path;
    }
}

final class StructuredAuditPersistenceClock implements AuditClock
{
    private int $microseconds = 0;

    public function now(): DateTimeImmutable
    {
        $suffix = str_pad((string) $this->microseconds++, 6, '0', STR_PAD_LEFT);
        return new DateTimeImmutable(
            '2026-09-09 18:00:00.' . $suffix,
            new DateTimeZone('UTC'),
        );
    }
}
