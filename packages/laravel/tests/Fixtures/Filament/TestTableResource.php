<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Resources\Resource;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

final class TestTableResource extends Resource
{
    protected static ?string $model = TestRecord::class;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->filters([
                Filter::make('status'),
                Filter::make('priority'),
            ])
            ->checkIfRecordIsSelectableUsing(
                static fn (TestRecord $record): bool => $record->name !== 'blocked',
            );
    }

    public static function getPages(): array
    {
        return [
            'index' => TestTablePage::route('/'),
        ];
    }
}
