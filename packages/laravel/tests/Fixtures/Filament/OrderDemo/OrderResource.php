<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Filament\Resources\Resource;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenantContext = app(OrderDemoTenantContext::class);

        if (! $tenantContext->shouldScopeResourceQuery()) {
            return $query;
        }

        return $query->where('tenant_id', $tenantContext->current());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'paid' => 'Paid',
                        'pending' => 'Pending',
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
