<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;

final class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    public static function getEloquentQuery(): Builder
    {
        $tenantId = app(OrderDemoTenantContext::class)->current();

        return parent::getEloquentQuery()->where('tenant_id', $tenantId);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
