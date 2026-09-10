<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\ListRecords;

class EmptyFilterTablePage extends ListRecords
{
    protected static string $resource = EmptyFilterTableResource::class;
}
