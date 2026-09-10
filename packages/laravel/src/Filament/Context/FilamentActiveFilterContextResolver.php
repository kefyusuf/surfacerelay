<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

/**
 * Snapshots the exact applied filter state from one trusted active Filament
 * table page. Pending/deferred form state is deliberately not consulted.
 */
final class FilamentActiveFilterContextResolver
{
    public const string EXTENSION_KEY = 'filament/active_filters';

    private const string IDENTITY_DOMAIN = "surfacerelay.filament.active-filters.v1\n";

    private readonly RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(
        private readonly Page $page,
        ?RuntimeScopeCanonicalizer $canonicalizer = null,
    ) {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function resolve(): ResolvedTrustedValue
    {
        if (! $this->page instanceof HasTable) {
            throw InvalidFilamentActiveFilterContext::unavailable();
        }

        try {
            $filters = $this->page->getTable()->getFilters();
            $snapshot = [];

            foreach (array_keys($filters) as $filterName) {
                $snapshot[$filterName] = $this->page->getTableFilterState($filterName);
            }
        } catch (\Throwable) {
            throw InvalidFilamentActiveFilterContext::resolutionFailed();
        }

        ksort($snapshot, SORT_STRING);

        try {
            $canonical = $this->canonicalizer->encode(
                $snapshot,
                'filament.active_filters',
            );
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentActiveFilterContext::unrepresentableState();
        }

        return new ResolvedTrustedValue(
            value: $snapshot,
            provenance: new ContextProvenance('filament.active_filters'),
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $canonical),
        );
    }
}
