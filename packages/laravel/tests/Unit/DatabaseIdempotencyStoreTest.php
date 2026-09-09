<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\MySqlBuilder;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Idempotency\CorruptIdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\DatabaseIdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecord;
use SurfaceRelay\Laravel\Idempotency\IdempotencyRecordState;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStoreUnavailable;

final class DatabaseIdempotencyStoreTest extends TestCase
{
    private const string TABLE = 'surfacerelay_idempotency_records';

    protected function tearDown(): void
    {
        MySqlBuilder::defaultTimePrecision(null);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        parent::tearDown();
    }

    public function test_database_store_types_are_available(): void
    {
        self::assertTrue(
            class_exists(DatabaseIdempotencyStore::class),
            'DatabaseIdempotencyStore must exist before durable-store behavior can pass.',
        );
        self::assertTrue(class_exists(IdempotencyStoreUnavailable::class));
    }

    public function test_first_claim_persists_exact_record_with_utc_second_precision(): void
    {
        $connection = $this->connection();
        $store = $this->store($connection);
        $fresh = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);

        $result = $store->claim($fresh, 100);

        self::assertTrue($result->claimed);
        self::assertSame($fresh, $result->record);
        self::assertEquals($fresh, $store->find($fresh->keyHash));

        $row = $connection->table(self::TABLE)->where('key_hash', $fresh->keyHash)->first();
        self::assertNotNull($row);
        self::assertSame('1970-01-01 00:01:40', $row->created_at);
        self::assertSame('1970-01-01 00:03:20', $row->expires_at);
    }

    public function test_duplicate_active_claim_returns_existing_record_without_replacing_it(): void
    {
        $store = $this->store($this->connection());
        $first = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);
        $competing = $this->record('a', 'c', IdempotencyRecordState::InProgress, 101, 300);

        self::assertTrue($store->claim($first, 100)->claimed);
        $result = $store->claim($competing, 101);

        self::assertFalse($result->claimed);
        self::assertEquals($first, $result->record);
        self::assertEquals($first, $store->find($first->keyHash));
    }

    public function test_expired_claim_is_atomically_replaced_and_equality_is_expired(): void
    {
        $store = $this->store($this->connection());
        $expired = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 110);
        $replacement = $this->record('a', 'c', IdempotencyRecordState::InProgress, 110, 210);

        self::assertTrue($store->claim($expired, 100)->claimed);
        $result = $store->claim($replacement, 110);

        self::assertTrue($result->claimed);
        self::assertEquals($replacement, $result->record);
        self::assertEquals($replacement, $store->find($replacement->keyHash));
    }

    public function test_active_completed_and_indeterminate_records_are_never_overwritten_by_claim(): void
    {
        $store = $this->store($this->connection());

        $completed = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);
        $store->claim($completed, 100);
        $store->complete($completed->keyHash, $completed->intentFingerprint, '{"ok":true}');
        $completedResult = $store->claim(
            $this->record('a', 'c', IdempotencyRecordState::InProgress, 101, 300),
            101,
        );

        self::assertFalse($completedResult->claimed);
        self::assertSame(IdempotencyRecordState::Completed, $completedResult->record->state);
        self::assertSame('{"ok":true}', $completedResult->record->outputPayload);

        $indeterminate = $this->record('d', 'e', IdempotencyRecordState::InProgress, 100, 200);
        $store->claim($indeterminate, 100);
        $store->markIndeterminate($indeterminate->keyHash, $indeterminate->intentFingerprint);
        $indeterminateResult = $store->claim(
            $this->record('d', 'f', IdempotencyRecordState::InProgress, 101, 300),
            101,
        );

        self::assertFalse($indeterminateResult->claimed);
        self::assertSame(IdempotencyRecordState::Indeterminate, $indeterminateResult->record->state);
        self::assertNull($indeterminateResult->record->outputPayload);
    }

    public function test_completion_is_guarded_by_exact_key_intent_and_in_progress_state(): void
    {
        $store = $this->store($this->connection());
        $record = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);
        $store->claim($record, 100);

        $this->assertTransitionFailsClosed(
            fn () => $store->complete($record->keyHash, str_repeat('c', 64), '{"secret":"x"}'),
            $record,
        );

        $store->complete($record->keyHash, $record->intentFingerprint, '{"ok":true}');
        $completed = $store->find($record->keyHash);
        self::assertNotNull($completed);
        self::assertSame(IdempotencyRecordState::Completed, $completed->state);
        self::assertSame('{"ok":true}', $completed->outputPayload);
        self::assertSame(100, $completed->createdAt);
        self::assertSame(200, $completed->expiresAt);

        $this->assertTransitionFailsClosed(
            fn () => $store->complete($record->keyHash, $record->intentFingerprint, '{"again":true}'),
            $record,
        );
    }

    public function test_indeterminate_transition_is_guarded_by_exact_key_intent_and_in_progress_state(): void
    {
        $store = $this->store($this->connection());
        $record = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);
        $store->claim($record, 100);

        $this->assertTransitionFailsClosed(
            fn () => $store->markIndeterminate($record->keyHash, str_repeat('c', 64)),
            $record,
        );

        $store->markIndeterminate($record->keyHash, $record->intentFingerprint);
        $indeterminate = $store->find($record->keyHash);
        self::assertNotNull($indeterminate);
        self::assertSame(IdempotencyRecordState::Indeterminate, $indeterminate->state);
        self::assertNull($indeterminate->outputPayload);
        self::assertSame(100, $indeterminate->createdAt);
        self::assertSame(200, $indeterminate->expiresAt);

        $this->assertTransitionFailsClosed(
            fn () => $store->markIndeterminate($record->keyHash, $record->intentFingerprint),
            $record,
        );
    }

    public function test_corrupt_state_and_timestamp_rows_fail_closed_without_stored_value_leakage(): void
    {
        $connection = $this->connection();
        $store = $this->store($connection);
        $keyHash = str_repeat('a', 64);
        $intent = str_repeat('b', 64);

        $connection->table(self::TABLE)->insert([
            'key_hash' => $keyHash,
            'intent_fingerprint' => $intent,
            'state' => 'secret-invalid-state',
            'output_payload' => null,
            'created_at' => '1970-01-01 00:01:40',
            'expires_at' => '1970-01-01 00:03:20',
        ]);

        try {
            $store->find($keyHash);
            self::fail('Invalid persisted state must fail closed.');
        } catch (CorruptIdempotencyRecord $e) {
            self::assertStringNotContainsString('secret-invalid-state', $e->getMessage());
            self::assertStringNotContainsString($keyHash, $e->getMessage());
        }

        $connection->table(self::TABLE)->delete();
        $connection->table(self::TABLE)->insert([
            'key_hash' => $keyHash,
            'intent_fingerprint' => $intent,
            'state' => IdempotencyRecordState::InProgress->value,
            'output_payload' => null,
            'created_at' => 'secret-invalid-time',
            'expires_at' => '1970-01-01 00:03:20',
        ]);

        try {
            $store->find($keyHash);
            self::fail('Invalid persisted timestamp must fail closed.');
        } catch (CorruptIdempotencyRecord $e) {
            self::assertStringNotContainsString('secret-invalid-time', $e->getMessage());
            self::assertStringNotContainsString($keyHash, $e->getMessage());
        }
    }

    public function test_database_errors_are_wrapped_statically_without_query_exception_chain_or_identity_leakage(): void
    {
        $connection = $this->connection(createSchema: false);
        $store = $this->store($connection, 'missing_idempotency_table');
        $record = $this->record('a', 'b', IdempotencyRecordState::InProgress, 100, 200);

        try {
            $store->claim($record, 100);
            self::fail('Database failure must fail closed.');
        } catch (IdempotencyStoreUnavailable $e) {
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('missing_idempotency_table', $e->getMessage());
            self::assertStringNotContainsString($record->keyHash, $e->getMessage());
            self::assertStringNotContainsString($record->intentFingerprint, $e->getMessage());
        }
    }

    public function test_package_migration_creates_exact_minimized_schema_without_raw_key_column(): void
    {
        $connection = $this->connection(createSchema: false);
        $container = new Container();
        $container->instance('db.schema', $connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);

        $path = dirname(__DIR__, 2)
            . '/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php';
        self::assertFileExists($path, 'The package must ship the T-402 idempotency migration artifact.');

        $migration = require $path;
        $migration->up();

        self::assertSame(
            ['key_hash', 'intent_fingerprint', 'state', 'output_payload', 'created_at', 'expires_at'],
            $connection->getSchemaBuilder()->getColumnListing(self::TABLE),
        );
        self::assertNotContains('idempotency_key', $connection->getSchemaBuilder()->getColumnListing(self::TABLE));

        $info = $connection->select('PRAGMA table_info(' . self::TABLE . ')');
        $keyColumn = array_values(array_filter($info, static fn (object $column): bool => $column->name === 'key_hash'))[0] ?? null;
        self::assertNotNull($keyColumn);
        self::assertSame(1, (int) $keyColumn->pk);

        $migration->down();
        self::assertFalse($connection->getSchemaBuilder()->hasTable(self::TABLE));
    }

    public function test_package_migration_round_trips_mysql_with_global_fractional_precision_enabled(): void
    {
        $connection = $this->mysqlConnection();
        MySqlBuilder::defaultTimePrecision(6);

        $container = new Container();
        $container->instance('db.schema', $connection->getSchemaBuilder());
        Facade::setFacadeApplication($container);

        $path = dirname(__DIR__, 2)
            . '/database/migrations/0000_00_00_000000_create_surfacerelay_idempotency_records.php';
        self::assertFileExists($path);
        $migration = require $path;

        $connection->getSchemaBuilder()->dropIfExists(self::TABLE);
        $migration->up();

        try {
            $store = $this->store($connection);
            $createdAt = 1788944400;
            $expiresAt = $createdAt + 86400;
            $fresh = $this->record('a', 'b', IdempotencyRecordState::InProgress, $createdAt, $expiresAt);

            self::assertTrue($store->claim($fresh, $createdAt)->claimed);

            $row = $connection->table(self::TABLE)->where('key_hash', $fresh->keyHash)->first();
            self::assertNotNull($row);
            self::assertSame(gmdate('Y-m-d H:i:s', $createdAt), $row->created_at);
            self::assertSame(gmdate('Y-m-d H:i:s', $expiresAt), $row->expires_at);
            self::assertEquals($fresh, $store->find($fresh->keyHash));
        } finally {
            $migration->down();
            $connection->disconnect();
        }
    }

    private function store(
        ConnectionInterface $connection,
        string $table = self::TABLE,
    ): DatabaseIdempotencyStore {
        self::assertTrue(
            class_exists(DatabaseIdempotencyStore::class),
            'DatabaseIdempotencyStore must exist before durable-store behavior can pass.',
        );

        return new DatabaseIdempotencyStore($connection, $table);
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

    private function createSchema(ConnectionInterface $connection): void
    {
        $connection->getSchemaBuilder()->create(self::TABLE, function (Blueprint $table): void {
            $table->char('key_hash', 64)->primary();
            $table->char('intent_fingerprint', 64);
            $table->string('state', 32);
            $table->text('output_payload')->nullable();
            $table->timestamp('created_at');
            $table->timestamp('expires_at')->index();
        });
    }

    private function record(
        string $keyChar,
        string $intentChar,
        IdempotencyRecordState $state,
        int $createdAt,
        int $expiresAt,
        ?string $outputPayload = null,
    ): IdempotencyRecord {
        return new IdempotencyRecord(
            keyHash: str_repeat($keyChar, 64),
            intentFingerprint: str_repeat($intentChar, 64),
            state: $state,
            outputPayload: $outputPayload,
            createdAt: $createdAt,
            expiresAt: $expiresAt,
        );
    }

    /** @param callable(): void $transition */
    private function assertTransitionFailsClosed(callable $transition, IdempotencyRecord $record): void
    {
        try {
            $transition();
            self::fail('Impossible idempotency transition must fail closed.');
        } catch (IdempotencyStoreUnavailable $e) {
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString($record->keyHash, $e->getMessage());
            self::assertStringNotContainsString($record->intentFingerprint, $e->getMessage());
        }
    }
}
