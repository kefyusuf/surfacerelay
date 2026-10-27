<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Quickstep provider: explicit invocation authorization plus the page wiring
 * (one import map to the published runtime bytes and the registration module).
 * The module self-guards: it registers nothing on surfaces without
 * document.modelContext or pages without a server-issued descriptor.
 */
final class QuickstartProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('view-quickstart-order', static function (User $actor, string $tenant, int $orderId): bool {
            return $tenant === \App\Support\FixedTenantResolver::TENANT
                && DB::table('quickstart_orders')
                    ->where('id', $orderId)
                    ->where('tenant_id', $tenant)
                    ->exists();
        });

        FilamentView::registerRenderHook(
            PanelsRenderHook::BODY_END,
            static function (): string {
                return sprintf(
                    <<<'HTML'
                    <script type="importmap">{"imports":{"@surfacerelay/browser-runtime":"%1$s"}}</script>
                    <script type="module" src="%2$s"></script>
                    HTML,
                    asset('js/browser-runtime/index.js'),
                    asset('js/quickstart-register.mjs'),
                );
            },
        );
    }
}
