<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Browser;

use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderResource;

/**
 * Real Filament panel for the T-807a browser proof. The order demo's trusted
 * actor/tenant come from the fixture harness, so the panel has no login.
 */
final class OrderDemoPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('order-demo')
            ->path('admin')
            ->resources([OrderResource::class])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                ValidateCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->renderHook(
                PanelsRenderHook::PAGE_END,
                static fn (): string => app(OrderDemoBindingScript::class)->render(),
            )
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                static fn (): string => '<script type="module" src="/surfacerelay/order-demo.mjs"></script>',
            );
    }
}
