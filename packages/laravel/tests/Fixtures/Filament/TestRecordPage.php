<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

final class TestRecordPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = TestRecordResource::class;

    protected string $view = 'filament-test-record-page';
}
