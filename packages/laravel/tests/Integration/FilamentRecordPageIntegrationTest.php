<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecordPage;

final class FilamentRecordPageIntegrationTest extends TestCase
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

    public function test_real_filament_record_page_exposes_exact_active_model_to_resolver(): void
    {
        $record = TestRecord::query()->create([
            'id' => 77,
            'name' => 'Visible only in app',
        ]);

        $page = new TestRecordPage();
        $page->record = $record;

        self::assertSame($record, $page->getRecord());

        $resolved = (new FilamentRecordContextResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertSame($record, $resolved->value);
        self::assertSame('filament.current_record', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
    }

    public function test_real_non_record_filament_resource_page_resolves_to_absence(): void
    {
        self::assertNull(
            (new FilamentRecordContextResolver(new NonRecordPage()))->resolve(),
        );
    }

    public function test_resolver_does_not_requery_real_filament_active_record(): void
    {
        $record = TestRecord::query()->create([
            'id' => 88,
            'name' => 'No re-query allowed',
        ]);
        $page = new TestRecordPage();
        $page->record = $record;

        $queries = 0;
        $this->app['db']->listen(static function () use (&$queries): void {
            $queries++;
        });
        $queries = 0;

        $resolved = (new FilamentRecordContextResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertSame($record, $resolved->value);
        self::assertSame(0, $queries, 'SurfaceRelay must not re-query the active Filament record.');
    }
}
