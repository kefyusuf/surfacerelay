<?php

declare(strict_types=1);

namespace App\Filament\Resources\A2Orders;

use App\A2\SingleMembershipTenantResolver;
use App\Filament\Resources\A2Orders\Pages\ListA2Orders;
use App\Models\A2Order;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

final class A2OrderResource extends Resource
{
    protected static ?string $model = A2Order::class;

    public static function getEloquentQuery(): Builder
    {
        $tenant = (new SingleMembershipTenantResolver())->resolve()?->value;
        return parent::getEloquentQuery()->where('tenant_id', $tenant ?? '__no_authorized_tenant__');
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id'), TextColumn::make('tenant_id')]);
    }

    public static function getPages(): array
    {
        return ['index' => ListA2Orders::route('/')];
    }
}
