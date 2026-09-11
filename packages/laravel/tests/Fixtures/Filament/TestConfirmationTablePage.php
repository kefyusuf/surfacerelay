<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;

final class TestConfirmationTablePage extends TestTablePage
{
    use InteractsWithSurfaceRelayConfirmation;
}
