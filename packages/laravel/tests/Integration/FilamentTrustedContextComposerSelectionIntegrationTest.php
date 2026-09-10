<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentTrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTableResource;

final class FilamentTrustedContextComposerSelectionIntegrationTest extends TestCase
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

    public function test_composes_actor_tenant_current_record_and_current_selection_in_canonical_order(): void
    {
        TestRecord::query()->create(['id' => 11, 'name' => 'record']);
        TestRecord::query()->create(['id' => 22, 'name' => 'selected']);

        $page = new SelectionComposerPage();
        $page->bootedInteractsWithTable();
        $page->resolvedRecord = TestRecord::query()->findOrFail(11);
        $page->selectedTableRecords = [22];

        $entries = (new FilamentTrustedContextComposer(
            new TrustedContextComposer(
                new SelectionComposerActorResolver(),
                new SelectionComposerTenantResolver(),
            ),
            new FilamentRecordContextResolver($page),
            new FilamentCurrentSelectionResolver($page),
        ))->resolve();

        self::assertSame(
            [
                'authenticated_actor',
                'tenant',
                'current_record',
                'current_selection',
            ],
            array_map(
                static fn (TrustedContextEntry $entry): string => $entry->requirement->value,
                $entries,
            ),
        );
        self::assertSame(11, (int) $entries[2]->value->getKey());
        self::assertSame([22], array_map(
            static fn (TestRecord $record): int => (int) $record->getKey(),
            $entries[3]->value,
        ));
        self::assertSame('filament.current_selection', $entries[3]->provenance->provider);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $entries[3]->confirmationScopeKey);
    }
}

final class SelectionComposerPage extends ListRecords
{
    protected static string $resource = TestTableResource::class;

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}

final class SelectionComposerActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            'actor-7',
            new ContextProvenance('test.auth'),
            'actor-scope',
        );
    }
}

final class SelectionComposerTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            'tenant-a',
            new ContextProvenance('test.tenant'),
            'tenant-scope',
        );
    }
}
