<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentCurrentSelection;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentCurrentSelectionResolverTest extends TestCase
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

    public function test_default_selection_ceiling_is_500(): void
    {
        self::assertSame(500, FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS);
    }

    public function test_selection_ceiling_must_be_positive(): void
    {
        foreach ([0, -1] as $invalidMaximum) {
            try {
                new FilamentCurrentSelectionResolver(
                    new NonRecordPage(),
                    maxSelectionRecords: $invalidMaximum,
                );
                self::fail('Expected InvalidFilamentCurrentSelection.');
            } catch (InvalidFilamentCurrentSelection $e) {
                self::assertSame(
                    'Filament current selection configuration is invalid.',
                    $e->getMessage(),
                );
                self::assertNull($e->getPrevious());
            }
        }
    }

    public function test_non_table_page_resolves_to_absence(): void
    {
        self::assertNull(
            (new FilamentCurrentSelectionResolver(new NonRecordPage()))->resolve(),
        );
    }

    public function test_table_with_empty_effective_selection_resolves_to_absence(): void
    {
        $page = $this->tablePage();

        self::assertNull(
            (new FilamentCurrentSelectionResolver($page))->resolve(),
        );
    }

    public function test_explicit_effective_selection_resolves_to_canonical_trusted_snapshot(): void
    {
        TestRecord::query()->create(['id' => 2, 'name' => 'B']);
        TestRecord::query()->create(['id' => 1, 'name' => 'A']);

        $page = $this->tablePage();
        $page->selectedTableRecords = [2, 1];

        $resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertSame([1, 2], array_map(
            static fn (TestRecord $record): int => (int) $record->getKey(),
            $resolved->value,
        ));
        self::assertSame('filament.current_selection', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
    }

    public function test_same_effective_set_has_same_scope_regardless_of_source_order(): void
    {
        TestRecord::query()->create(['id' => 1, 'name' => 'A']);
        TestRecord::query()->create(['id' => 2, 'name' => 'B']);
        TestRecord::query()->create(['id' => 3, 'name' => 'C']);

        $pageAB = $this->tablePage();
        $pageAB->selectedTableRecords = [1, 2];
        $ab = (new FilamentCurrentSelectionResolver($pageAB))->resolve();

        $pageBA = $this->tablePage();
        $pageBA->selectedTableRecords = [2, 1];
        $ba = (new FilamentCurrentSelectionResolver($pageBA))->resolve();

        $pageAC = $this->tablePage();
        $pageAC->selectedTableRecords = [1, 3];
        $ac = (new FilamentCurrentSelectionResolver($pageAC))->resolve();

        self::assertNotNull($ab);
        self::assertNotNull($ba);
        self::assertNotNull($ac);
        self::assertSame($ab->confirmationScopeKey, $ba->confirmationScopeKey);
        self::assertNotSame($ab->confirmationScopeKey, $ac->confirmationScopeKey);
        self::assertSame(
            array_map(static fn (TestRecord $record): int => (int) $record->getKey(), $ab->value),
            array_map(static fn (TestRecord $record): int => (int) $record->getKey(), $ba->value),
        );
    }

    private function tablePage(): TestTablePage
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();

        return $page;
    }
}
