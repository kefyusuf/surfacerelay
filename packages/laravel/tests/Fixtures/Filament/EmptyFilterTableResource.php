<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Resource;
use Filament\Tables\Table;

final class EmptyFilterTableResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function table(Table $table): Table
    {
        return $table->columns([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => EmptyFilterTablePage::route('/'),
        ];
    }
}
