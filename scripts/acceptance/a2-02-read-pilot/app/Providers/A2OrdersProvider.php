<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
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

        // A2-08 app-owned page wiring: inject the registration module on every
        // panel page. The module self-guards — it registers only on a surface
        // that exposes document.modelContext AND a component that carries the
        // server-issued descriptor, so the login page and other pages register
        // nothing. This injects discovery metadata delivery only; it issues no
        // authority and the server re-checks every dispatch.
        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            static function (): string {
                return sprintf(
                    <<<'HTML'
                    <script type="importmap">{"imports":{"@surfacerelay/browser-runtime":"%1$s"}}</script>
                    <script type="module" src="%2$s"></script>
                    HTML,
                    asset('js/browser-runtime/index.js'),
                    asset('js/a2-surfacerelay-page.mjs'),
                );
            },
        );
    }
}
