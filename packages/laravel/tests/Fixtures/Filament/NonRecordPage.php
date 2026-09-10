<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Page;

final class NonRecordPage extends Page
{
    protected static string $resource = TestRecordResource::class;

    protected string $view = 'filament-test-non-record-page';
}
