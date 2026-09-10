<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Resource;

final class TestRecordResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function getPages(): array
    {
        return [
            'index' => NonRecordPage::route('/'),
            'record' => TestRecordPage::route('/{record}'),
        ];
    }
}
