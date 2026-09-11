<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Illuminate\Database\Eloquent\Model;

final class Order extends Model
{
    protected $table = 'filament_order_demo_orders';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'held' => 'boolean',
        'refunded' => 'boolean',
    ];
}
