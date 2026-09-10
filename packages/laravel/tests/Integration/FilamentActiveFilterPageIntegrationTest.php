<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Schema\Blueprint;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentActiveFilterContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\FilamentInvocationContextFactory;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentActiveFilterContext;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestTablePage;

final class FilamentActiveFilterPageIntegrationTest extends TestCase
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

    public function test_no_exposure_preserves_existing_filament_context_without_trusted_extensions(): void
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();

        $context = $this->factory()->forPage(
            page: $page,
            surface: 'filament',
            correlationId: 'corr-no-filter-exposure',
            metadata: ['filament/active_filters' => ['spoofed' => true]],
        );

        self::assertSame([], $context->allTrustedExtensions());
    }

    public function test_deferred_pending_filter_form_state_is_not_authority_until_filament_applies_it(): void
    {
        $page = new TestTablePage();
        $page->bootedInteractsWithTable();

        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => true],
            'priority' => ['isActive' => false],
        ]);
        $page->applyTableFilters();

        $appliedA = $this->publicAppliedSnapshot($page, ['priority', 'status']);
        $contextA = $this->contextWithActiveFilters($page, 'corr-filter-A');
        $extensionA = $contextA->requireTrustedExtension(FilamentActiveFilterContextResolver::EXTENSION_KEY);

        self::assertSame($appliedA, $extensionA->value);

        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => false],
            'priority' => ['isActive' => true],
        ]);

        self::assertNotSame(
            $page->getTableFilterState('status'),
            $page->getTableFilterFormState('status'),
        );

        $contextPendingB = $this->contextWithActiveFilters($page, 'corr-filter-pending-B');
        $pendingExtension = $contextPendingB->requireTrustedExtension(
            FilamentActiveFilterContextResolver::EXTENSION_KEY,
        );

        self::assertSame($appliedA, $pendingExtension->value);
        self::assertSame($extensionA->scopeKey, $pendingExtension->scopeKey);

        $page->applyTableFilters();
        $appliedB = $this->publicAppliedSnapshot($page, ['priority', 'status']);
        $contextB = $this->contextWithActiveFilters($page, 'corr-filter-B');
        $extensionB = $contextB->requireTrustedExtension(FilamentActiveFilterContextResolver::EXTENSION_KEY);

        self::assertSame($appliedB, $extensionB->value);
        self::assertNotSame($appliedA, $appliedB);
        self::assertNotSame($extensionA->scopeKey, $extensionB->scopeKey);
    }

    public function test_live_filter_state_is_observed_immediately_through_query_facing_public_state(): void
    {
        $page = new LiveFilterTablePage();
        $page->bootedInteractsWithTable();

        $page->getTableFiltersForm()->fill([
            'status' => ['isActive' => true],
        ]);

        $context = $this->contextWithActiveFilters($page, 'corr-live-filter');
        $extension = $context->requireTrustedExtension(FilamentActiveFilterContextResolver::EXTENSION_KEY);

        self::assertSame(
            ['status' => $page->getTableFilterState('status')],
            $extension->value,
        );
        self::assertSame(
            $page->getTableFilterState('status'),
            $page->getTableFilterFormState('status'),
        );
    }

    public function test_explicit_active_filter_exposure_on_non_table_page_fails_closed(): void
    {
        try {
            $this->factory()->forPage(
                page: new NonRecordPage(),
                surface: 'filament',
                correlationId: 'corr-non-table-filter',
                contextExposure: FilamentContextExposure::activeFilters(),
            );
            self::fail('Expected explicit active-filter exposure to fail for a non-table page.');
        } catch (InvalidFilamentActiveFilterContext $e) {
            self::assertSame(
                'Filament active-filter context is unavailable for this page.',
                $e->getMessage(),
            );
            self::assertNull($e->getPrevious());
        }
    }

    private function factory(): FilamentInvocationContextFactory
    {
        return new FilamentInvocationContextFactory(new TrustedContextComposer(
            new NullActiveFilterActorResolver(),
            new NullActiveFilterTenantResolver(),
        ));
    }

    private function contextWithActiveFilters(
        \Filament\Resources\Pages\Page $page,
        string $correlationId,
    ): \SurfaceRelay\Laravel\Runtime\InvocationContext {
        return $this->factory()->forPage(
            page: $page,
            surface: 'filament',
            correlationId: $correlationId,
            contextExposure: FilamentContextExposure::activeFilters(),
        );
    }

    /** @param list<string> $filterNames */
    private function publicAppliedSnapshot(TestTablePage|LiveFilterTablePage $page, array $filterNames): array
    {
        $snapshot = [];
        foreach ($filterNames as $filterName) {
            $snapshot[$filterName] = $page->getTableFilterState($filterName);
        }
        ksort($snapshot, SORT_STRING);

        return $snapshot;
    }
}

final class NullActiveFilterActorResolver implements AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class NullActiveFilterTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        return null;
    }
}

final class LiveFilterTableResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->filters([
                Filter::make('status'),
            ])
            ->deferFilters(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => LiveFilterTablePage::route('/'),
        ];
    }
}

final class LiveFilterTablePage extends ListRecords
{
    protected static string $resource = LiveFilterTableResource::class;
}
