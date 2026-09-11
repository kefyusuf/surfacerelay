<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;

final class TestConfirmationTablePage extends TestTablePage
{
    use InteractsWithSurfaceRelayConfirmation;

    /**
     * Direct integration tests reuse one PHP Page instance across synthetic
     * invocations. Real Livewire requests reconstruct this protected cache on
     * each request, so clear it here before each trusted selection snapshot.
     */
    public function getSelectedTableRecords(
        bool $shouldFetchSelectedRecords = true,
        ?int $chunkSize = null,
    ): EloquentCollection | Collection | LazyCollection {
        unset($this->cachedSelectedTableRecords);

        return parent::getSelectedTableRecords($shouldFetchSelectedRecords, $chunkSize);
    }
}
