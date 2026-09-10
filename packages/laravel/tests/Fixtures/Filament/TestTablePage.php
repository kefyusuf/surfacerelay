<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\ListRecords;

final class TestTablePage extends ListRecords
{
    protected static string $resource = TestTableResource::class;
}
