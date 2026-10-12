<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/** Explicit invocation authorization. Discovery is separately gated. */
final class A2OrdersProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('a2.orders.inspect', static function (User $actor, string $tenant, int $orderId): bool {
            return DB::table('a2_memberships')
                ->where('user_id', $actor->getAuthIdentifier())
                ->where('tenant_id', $tenant)
                ->where('can_inspect', true)
                ->exists()
                && DB::table('a2_orders')
                    ->where('id', $orderId)
                    ->where('tenant_id', $tenant)
                    ->exists();
        });
    }
}
