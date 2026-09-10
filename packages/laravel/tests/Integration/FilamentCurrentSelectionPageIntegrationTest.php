<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentCurrentSelection;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentCurrentSelectionPageIntegrationTest extends TestCase
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

    public function test_explicit_selection_matches_filament_public_effective_result(): void
    {
        TestRecord::query()->create(['id' => 1, 'name' => 'A']);
        TestRecord::query()->create(['id' => 2, 'name' => 'B']);
        TestRecord::query()->create(['id' => 3, 'name' => 'C']);

        $page = $this->page();
        $page->selectedTableRecords = [2, 1];

        $filamentRecords = $page->getSelectedTableRecords(true, 100)->values()->all();
        $resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertEqualsCanonicalizing($filamentRecords, $resolved->value);
        self::assertSame([1, 2], $this->recordIds($resolved->value));
    }

    public function test_raw_empty_selected_keys_can_still_resolve_non_empty_select_all_authority(): void
    {
        TestRecord::query()->create(['id' => 1, 'name' => 'A']);
        $recordB = TestRecord::query()->create(['id' => 2, 'name' => 'B']);
        TestRecord::query()->create(['id' => 3, 'name' => 'C']);

        $page = $this->page();
        $page->isTrackingDeselectedTableRecords = true;
        $page->selectedTableRecords = [];
        $page->deselectedTableRecords = [(string) $recordB->getKey()];

        self::assertSame([], $page->selectedTableRecords);

        $filamentRecords = $page->getSelectedTableRecords(true, 100)->values()->all();
        $resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

        self::assertSame([1, 3], $this->recordIds($filamentRecords));
        self::assertNotNull($resolved);
        self::assertSame([1, 3], $this->recordIds($resolved->value));
    }

    public function test_filament_selectability_filtering_is_preserved_in_effective_authority(): void
    {
        TestRecord::query()->create(['id' => 1, 'name' => 'allowed']);
        TestRecord::query()->create(['id' => 2, 'name' => 'blocked']);
        TestRecord::query()->create(['id' => 3, 'name' => 'allowed-again']);

        $page = $this->page();
        $page->selectedTableRecords = [1, 2, 3];

        $filamentRecords = $page->getSelectedTableRecords(true, 100)->values()->all();
        $resolved = (new FilamentCurrentSelectionResolver($page))->resolve();

        self::assertSame([1, 3], $this->recordIds($filamentRecords));
        self::assertNotNull($resolved);
        self::assertSame([1, 3], $this->recordIds($resolved->value));
    }

    public function test_bounded_select_all_fails_on_third_effective_record_without_unbounded_materialization(): void
    {
        foreach (range(1, 5) as $id) {
            TestRecord::query()->create(['id' => $id, 'name' => 'record-' . $id]);
        }

        $page = $this->page();
        $page->isTrackingDeselectedTableRecords = true;
        $page->selectedTableRecords = [];
        $page->deselectedTableRecords = [];

        $queries = [];
        $this->app['db']->listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        try {
            (new FilamentCurrentSelectionResolver($page, maxSelectionRecords: 2))->resolve();
            self::fail('Expected selection limit failure.');
        } catch (InvalidFilamentCurrentSelection $e) {
            self::assertSame(
                'Filament current selection exceeds the configured limit.',
                $e->getMessage(),
            );
            self::assertNull($e->getPrevious());
        }

        self::assertTrue(
            array_any(
                $queries,
                static fn (string $sql): bool => str_contains($sql, 'filament_test_records')
                    && str_contains($sql, 'limit 3'),
            ),
            'Filament lazy selection query must use a first decision window of three rows for max=2.',
        );
    }

    public function test_bounded_selection_above_100_hydrates_only_max_plus_one_records(): void
    {
        foreach (range(1, 200) as $id) {
            TestRecord::query()->create(['id' => $id, 'name' => 'record-' . $id]);
        }

        $page = $this->page();
        $page->isTrackingDeselectedTableRecords = true;
        $page->selectedTableRecords = [];
        $page->deselectedTableRecords = [];

        $hydrated = 0;
        TestRecord::retrieved(static function () use (&$hydrated): void {
            $hydrated++;
        });

        try {
            (new FilamentCurrentSelectionResolver($page, maxSelectionRecords: 150))->resolve();
            self::fail('Expected selection limit failure.');
        } catch (InvalidFilamentCurrentSelection $e) {
            self::assertSame(
                'Filament current selection exceeds the configured limit.',
                $e->getMessage(),
            );
            self::assertNull($e->getPrevious());
        }

        self::assertSame(
            151,
            $hydrated,
            'SurfaceRelay must not cause Filament to hydrate beyond the max+1 decision window.',
        );
    }

    private function page(): TestTablePage
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();

        return $page;
    }

    /** @param list<TestRecord> $records @return list<int> */
    private function recordIds(array $records): array
    {
        return array_map(
            static fn (TestRecord $record): int => (int) $record->getKey(),
            $records,
        );
    }
}
