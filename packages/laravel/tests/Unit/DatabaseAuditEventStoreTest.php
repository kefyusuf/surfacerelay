<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditEvent;
use SurfaceRelay\Laravel\Audit\AuditOutcomeKind;
use SurfaceRelay\Laravel\Audit\AuditStoreUnavailable;
use SurfaceRelay\Laravel\Audit\AuditTrustedContextEntry;
use SurfaceRelay\Laravel\Audit\DatabaseAuditEventStore;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;

final class DatabaseAuditEventStoreTest extends TestCase
{
    private const string TABLE = 'surfacerelay_audit_events';

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_database_audit_store_types_exist(): void
    {
        self::assertTrue(class_exists(DatabaseAuditEventStore::class), 'DatabaseAuditEventStore must exist.');
        self::assertTrue(class_exists(AuditStoreUnavailable::class), 'AuditStoreUnavailable must exist.');
    }

    public function test_append_inserts_exact_allowlisted_completed_row_and_index_hashes(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection();
        $event = $this->event(
            eventId: str_repeat('a', 32),
            correlationId: 'corr-unbounded-value',
        );

        (new DatabaseAuditEventStore($connection))->append($event);

        $row = $connection->table(self::TABLE)->where('event_id', $event->eventId)->first();
        self::assertNotNull($row);
        self::assertSame('2026-09-09 16:20:30.123456', $row->recorded_at);
        self::assertSame($event->correlationId, $row->correlation_id);
        self::assertSame(
            hash('sha256', "surfacerelay.audit.correlation.v1\n" . $event->correlationId),
            $row->correlation_hash,
        );
        self::assertSame($event->surface, $row->surface);
        self::assertSame($event->actionId, $row->action_id);
        self::assertSame($event->actionVersion, (int) $row->action_version);
        self::assertSame($event->actionScope->value, $row->action_scope);
        self::assertSame($event->actionEffect->value, $row->action_effect);
        self::assertSame($event->actionRisk->value, $row->action_risk);
        self::assertSame($event->idempotencyPolicy->value, $row->idempotency_policy);
        self::assertSame($event->outputSensitivity->value, $row->output_sensitivity);
        self::assertSame($event->outputContentTrust->value, $row->output_content_trust);
        self::assertSame('completed', $row->outcome_kind);
        self::assertNull($row->halted_at);
        self::assertNull($row->halt_code);
        self::assertNull($row->halt_code_hash);
        self::assertSame(0, (int) $row->human_confirmation_present);
        self::assertSame(
            [['requirement' => 'tenant', 'provider' => 'tenant.provider']],
            json_decode($row->trusted_context_manifest, true, flags: JSON_THROW_ON_ERROR),
        );
    }

    public function test_halted_event_persists_stage_code_and_domain_separated_halt_hash(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection();
        $event = $this->event(
            eventId: str_repeat('b', 32),
            outcomeKind: AuditOutcomeKind::Halted,
            haltedAt: ActionPipelineStage::OutputPolicy,
            haltCode: 'output_policy_failed',
            humanConfirmationPresent: true,
        );

        (new DatabaseAuditEventStore($connection))->append($event);

        $row = $connection->table(self::TABLE)->where('event_id', $event->eventId)->first();
        self::assertNotNull($row);
        self::assertSame('halted', $row->outcome_kind);
        self::assertSame('output_policy', $row->halted_at);
        self::assertSame('output_policy_failed', $row->halt_code);
        self::assertSame(
            hash('sha256', "surfacerelay.audit.halt-code.v1\noutput_policy_failed"),
            $row->halt_code_hash,
        );
        self::assertSame(1, (int) $row->human_confirmation_present);
    }

    public function test_duplicate_event_id_fails_closed_without_mutating_existing_row(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection();
        $store = new DatabaseAuditEventStore($connection);
        $first = $this->event(eventId: str_repeat('c', 32), correlationId: 'first-correlation');
        $second = $this->event(eventId: str_repeat('c', 32), correlationId: 'second-correlation');

        $store->append($first);

        try {
            $store->append($second);
            self::fail('Duplicate event id must fail rather than update/replace/ignore.');
        } catch (AuditStoreUnavailable $e) {
            self::assertSame('Audit store operation failed.', $e->getMessage());
            self::assertNull($e->getPrevious());
        }

        $rows = $connection->table(self::TABLE)->get();
        self::assertCount(1, $rows);
        self::assertSame('first-correlation', $rows->first()->correlation_id);
        self::assertSame(
            hash('sha256', "surfacerelay.audit.correlation.v1\nfirst-correlation"),
            $rows->first()->correlation_hash,
        );
    }

    public function test_database_failure_is_wrapped_statically_without_query_diagnostics_or_chain(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection(createSchema: false);
        $event = $this->event(eventId: str_repeat('d', 32), correlationId: 'SECRET_CORRELATION');

        try {
            (new DatabaseAuditEventStore($connection, 'SECRET_MISSING_AUDIT_TABLE'))->append($event);
            self::fail('Database failure must fail closed.');
        } catch (AuditStoreUnavailable $e) {
            self::assertSame('Audit store operation failed.', $e->getMessage());
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('SECRET_MISSING_AUDIT_TABLE', $e->getMessage());
            self::assertStringNotContainsString('SECRET_CORRELATION', $e->getMessage());
        }
    }

    public function test_manifest_encoding_failure_is_static_non_chained_and_leak_free(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection();
        $event = $this->event(
            eventId: str_repeat('e', 32),
            trustedContextManifest: [
                new AuditTrustedContextEntry(ContextRequirement::Tenant, "provider-\xB1"),
            ],
        );

        try {
            (new DatabaseAuditEventStore($connection))->append($event);
            self::fail('Invalid UTF-8 manifest must fail closed.');
        } catch (AuditStoreUnavailable $e) {
            self::assertSame('Audit event encoding failed.', $e->getMessage());
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('provider-', $e->getMessage());
        }

        self::assertSame(0, $connection->table(self::TABLE)->count());
    }

    public function test_package_migration_creates_exact_minimized_schema_and_indexes_then_round_trips_down(): void
    {
        $this->assertTypesExist();
        $connection = $this->connection(createSchema: false);
        $this->installSchemaFacade($connection);
        $migration = $this->packageMigration();

        $migration->up();

        self::assertSame([
            'event_id',
            'recorded_at',
            'correlation_id',
            'correlation_hash',
            'surface',
            'action_id',
            'action_version',
            'action_scope',
            'action_effect',
            'action_risk',
            'idempotency_policy',
            'output_sensitivity',
            'output_content_trust',
            'outcome_kind',
            'halted_at',
            'halt_code',
            'halt_code_hash',
            'human_confirmation_present',
            'trusted_context_manifest',
        ], $connection->getSchemaBuilder()->getColumnListing(self::TABLE));

        foreach ([
            'input', 'output', 'metadata', 'idempotency_key', 'binding_id',
            'confirmation_receipt', 'actor_id', 'tenant_id',
        ] as $forbiddenColumn) {
            self::assertFalse($connection->getSchemaBuilder()->hasColumn(self::TABLE, $forbiddenColumn));
        }

        $indexes = $connection->select('PRAGMA index_list(' . self::TABLE . ')');
        $indexNames = array_map(static fn (object $row): string => (string) $row->name, $indexes);
        self::assertContains('surfacerelay_audit_events_recorded_at_index', $indexNames);
        self::assertContains('surfacerelay_audit_events_correlation_hash_index', $indexNames);
        self::assertContains('sr_audit_action_recorded_idx', $indexNames);
        self::assertContains('sr_audit_outcome_halt_recorded_idx', $indexNames);

        $migration->down();
        self::assertFalse($connection->getSchemaBuilder()->hasTable(self::TABLE));
    }

    public function test_package_migration_round_trips_utc_microseconds_on_mysql(): void
    {
        $this->assertTypesExist();
        $connection = $this->mysqlConnection();
        $this->installSchemaFacade($connection);
        $migration = $this->packageMigration();
        $connection->getSchemaBuilder()->dropIfExists(self::TABLE);
        $migration->up();

        try {
            $event = $this->event(eventId: str_repeat('f', 32));
            (new DatabaseAuditEventStore($connection))->append($event);

            $row = $connection->table(self::TABLE)->where('event_id', $event->eventId)->first();
            self::assertNotNull($row);
            self::assertSame('2026-09-09 16:20:30.123456', $row->recorded_at);
            self::assertSame('[]', json_encode([], JSON_THROW_ON_ERROR));
        } finally {
            $migration->down();
            $connection->disconnect();
        }
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(class_exists(DatabaseAuditEventStore::class), 'DatabaseAuditEventStore must exist.');
        self::assertTrue(class_exists(AuditStoreUnavailable::class), 'AuditStoreUnavailable must exist.');
    }

    /**
     * @param list<AuditTrustedContextEntry>|null $trustedContextManifest
     */
    private function event(
        string $eventId,
        string $correlationId = 'corr-404',
        AuditOutcomeKind $outcomeKind = AuditOutcomeKind::Completed,
        ?ActionPipelineStage $haltedAt = null,
        ?string $haltCode = null,
        bool $humanConfirmationPresent = false,
        ?array $trustedContextManifest = null,
    ): AuditEvent {
        return new AuditEvent(
            eventId: $eventId,
            recordedAt: new DateTimeImmutable('2026-09-09 16:20:30.123456', new DateTimeZone('UTC')),
            correlationId: $correlationId,
            surface: 'webmcp-surface-without-runtime-length-limit',
            actionId: 'audit.persist',
            actionVersion: 3,
            actionScope: ActionScope::Portable,
            actionEffect: ActionEffect::ReversibleWrite,
            actionRisk: ActionRisk::Consequential,
            idempotencyPolicy: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::ContainsUntrustedContent,
            outcomeKind: $outcomeKind,
            haltedAt: $haltedAt,
            haltCode: $haltCode,
            humanConfirmationPresent: $humanConfirmationPresent,
            trustedContextManifest: $trustedContextManifest ?? [
                new AuditTrustedContextEntry(ContextRequirement::Tenant, 'tenant.provider'),
            ],
        );
    }

    private function connection(bool $createSchema = true): ConnectionInterface
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $connection = $capsule->getConnection();

        if ($createSchema) {
            $this->createSchema($connection);
        }

        return $connection;
    }

    private function createSchema(ConnectionInterface $connection): void
    {
        $connection->getSchemaBuilder()->create(self::TABLE, function (Blueprint $table): void {
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
    }

    private function installSchemaFacade(ConnectionInterface $connection): void
    {
        $container = new Container();
        $container->instance('db.schema', $connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);
    }

    private function packageMigration(): object
    {
        $path = dirname(__DIR__, 2)
            . '/database/migrations/0000_00_00_000001_create_surfacerelay_audit_events.php';
        self::assertFileExists($path, 'The package must ship the T-404 audit migration.');

        return require $path;
    }

    private function mysqlConnection(): ConnectionInterface
    {
        $host = getenv('SURFACERELAY_TEST_MYSQL_HOST');
        if ($host === false || $host === '') {
            self::markTestSkipped('MySQL integration environment is not configured.');
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => (int) (getenv('SURFACERELAY_TEST_MYSQL_PORT') ?: 3306),
            'database' => getenv('SURFACERELAY_TEST_MYSQL_DATABASE') ?: 'surfacerelay_test',
            'username' => getenv('SURFACERELAY_TEST_MYSQL_USERNAME') ?: 'root',
            'password' => getenv('SURFACERELAY_TEST_MYSQL_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'timezone' => '+00:00',
        ]);

        return $capsule->getConnection();
    }
}
