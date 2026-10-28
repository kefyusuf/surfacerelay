<?php

declare(strict_types=1);

namespace App\Filament\Resources\QuickstartOrders;

use App\Filament\Resources\QuickstartOrders\Pages\ListQuickstartOrders;
use Filament\Resources\Resource;

final class QuickstartOrderResource extends Resource
{
    protected static ?string $model = \App\Models\QuickstartOrder::class;

    protected static ?string $slug = 'quickstart-orders';

    public static function getPages(): array
    {
        return [
            'index' => ListQuickstartOrders::route('/'),
        ];
    }
}
