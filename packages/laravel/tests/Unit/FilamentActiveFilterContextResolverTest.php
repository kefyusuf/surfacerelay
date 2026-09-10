<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use RuntimeException;
use SurfaceRelay\Laravel\Filament\Context\FilamentActiveFilterContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentActiveFilterContext;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\EmptyFilterTablePage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentActiveFilterContextResolverTest extends TestCase
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

    public function test_active_filter_exposure_is_an_explicit_typed_server_side_value(): void
    {
        $exposure = FilamentContextExposure::activeFilters();

        self::assertTrue($exposure->includesActiveFilters());
    }

    public function test_resolver_declares_exact_namespaced_extension_key(): void
    {
        self::assertSame(
            'filament/active_filters',
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );
    }

    public function test_explicit_resolution_on_non_table_page_fails_closed(): void
    {
        try {
            (new FilamentActiveFilterContextResolver(new NonRecordPage()))->resolve();
            self::fail('Expected active-filter context availability failure.');
        } catch (InvalidFilamentActiveFilterContext $e) {
            self::assertSame(
                'Filament active-filter context is unavailable for this page.',
                $e->getMessage(),
            );
            self::assertNull($e->getPrevious());
        }
    }

    public function test_table_with_no_visible_configured_filters_resolves_present_empty_snapshot(): void
    {
        $page = new EmptyFilterTablePage();
        $page->bootedInteractsWithTable();

        $resolved = (new FilamentActiveFilterContextResolver($page))->resolve();

        self::assertSame([], $resolved->value);
        self::assertSame('filament.active_filters', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
    }

    public function test_resolver_snapshots_exact_public_applied_state_for_each_configured_filter(): void
    {
        $page = $this->controlledPage([
            'status' => [
                'isActive' => false,
                'count' => 0,
                'label' => '',
                'none' => null,
                'items' => [],
            ],
            'priority' => [
                'values' => [3, 1],
            ],
        ]);

        $resolved = (new FilamentActiveFilterContextResolver($page))->resolve();

        self::assertSame([
            'priority' => ['values' => [3, 1]],
            'status' => [
                'isActive' => false,
                'count' => 0,
                'label' => '',
                'none' => null,
                'items' => [],
            ],
        ], $resolved->value);
        self::assertSame($page->getTableFilterState('status'), $resolved->value['status']);
        self::assertSame($page->getTableFilterState('priority'), $resolved->value['priority']);
        self::assertSame('filament.active_filters', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
    }

    public function test_associative_insertion_order_does_not_change_scope_key(): void
    {
        $pageA = $this->controlledPage([
            'status' => [
                'outer' => ['z' => 2, 'a' => 1],
                'isActive' => true,
            ],
            'priority' => ['values' => ['high', 'low']],
        ]);
        $pageB = $this->controlledPage([
            'priority' => ['values' => ['high', 'low']],
            'status' => [
                'isActive' => true,
                'outer' => ['a' => 1, 'z' => 2],
            ],
        ]);

        $a = (new FilamentActiveFilterContextResolver($pageA))->resolve();
        $b = (new FilamentActiveFilterContextResolver($pageB))->resolve();

        self::assertSame($a->confirmationScopeKey, $b->confirmationScopeKey);
    }

    public function test_list_order_and_scalar_types_remain_scope_significant(): void
    {
        $one = (new FilamentActiveFilterContextResolver($this->controlledPage([
            'status' => ['values' => [1, '1']],
            'priority' => [],
        ])))->resolve();
        $reordered = (new FilamentActiveFilterContextResolver($this->controlledPage([
            'status' => ['values' => ['1', 1]],
            'priority' => [],
        ])))->resolve();
        $stringOnly = (new FilamentActiveFilterContextResolver($this->controlledPage([
            'status' => ['values' => ['1']],
            'priority' => [],
        ])))->resolve();
        $intOnly = (new FilamentActiveFilterContextResolver($this->controlledPage([
            'status' => ['values' => [1]],
            'priority' => [],
        ])))->resolve();

        self::assertNotSame($one->confirmationScopeKey, $reordered->confirmationScopeKey);
        self::assertNotSame($stringOnly->confirmationScopeKey, $intOnly->confirmationScopeKey);
    }

    public function test_unrepresentable_applied_filter_state_fails_closed_without_value_leakage(): void
    {
        $page = $this->controlledPage([
            'status' => ['payload' => new OpaqueFilamentFilterState('SECRET-FILTER-VALUE')],
            'priority' => [],
        ]);

        try {
            (new FilamentActiveFilterContextResolver($page))->resolve();
            self::fail('Expected unrepresentable active-filter state failure.');
        } catch (InvalidFilamentActiveFilterContext $e) {
            self::assertSame(
                'Filament active-filter state is not deterministically representable.',
                $e->getMessage(),
            );
            self::assertStringNotContainsString('SECRET-FILTER-VALUE', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function test_framework_state_read_failure_is_static_safe_and_non_chained(): void
    {
        $page = $this->controlledPage([]);
        $page->throwOnFilterState = true;

        try {
            (new FilamentActiveFilterContextResolver($page))->resolve();
            self::fail('Expected active-filter resolution failure.');
        } catch (InvalidFilamentActiveFilterContext $e) {
            self::assertSame('Filament active-filter context resolution failed.', $e->getMessage());
            self::assertStringNotContainsString('SECRET-FILTER-ERROR', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    /** @param array<string, array<string, mixed>|null> $states */
    private function controlledPage(array $states): ControlledFilterStatePage
    {
        $page = new ControlledFilterStatePage();
        $page->bootedInteractsWithTable();
        $page->providedFilterStates = $states;

        return $page;
    }
}

final class ControlledFilterStatePage extends TestTablePage
{
    /** @var array<string, array<string, mixed>|null> */
    public array $providedFilterStates = [];

    public bool $throwOnFilterState = false;

    public function getTableFilterState(string $name): ?array
    {
        if ($this->throwOnFilterState) {
            throw new RuntimeException('SECRET-FILTER-ERROR');
        }

        return $this->providedFilterStates[$name] ?? null;
    }
}

final class OpaqueFilamentFilterState
{
    public function __construct(public string $secret) {}
}
