<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;

final class TestConfirmationRecordPage extends Page
{
    use InteractsWithRecord;
    use InteractsWithSurfaceRelayConfirmation;

    protected static string $resource = TestRecordResource::class;

    protected string $view = 'filament-test-confirmation-page';
}
