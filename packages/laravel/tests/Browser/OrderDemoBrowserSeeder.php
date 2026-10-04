<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Browser;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;

/** Seeds the documented order demo layout (examples/filament-orders/README.md). */
final class OrderDemoBrowserSeeder extends Seeder
{
    public function run(): void
    {
        self::reset();
    }

    public static function reset(): void
    {
        if (! Schema::hasTable('filament_order_demo_orders')) {
            Schema::create('filament_order_demo_orders', static function (Blueprint $table): void {
                $table->integer('id')->primary();
                $table->string('tenant_id');
                $table->string('status');
                $table->boolean('held')->default(false);
                $table->boolean('refunded')->default(false);
            });
        }

        Order::query()->delete();
        Order::query()->insert([
            ['id' => 101, 'tenant_id' => 'tenant-a', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 102, 'tenant_id' => 'tenant-a', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 103, 'tenant_id' => 'tenant-a', 'status' => 'pending', 'held' => false, 'refunded' => false],
            ['id' => 201, 'tenant_id' => 'tenant-b', 'status' => 'paid', 'held' => false, 'refunded' => false],
            ['id' => 202, 'tenant_id' => 'tenant-b', 'status' => 'pending', 'held' => false, 'refunded' => false],
        ]);
    }
}
