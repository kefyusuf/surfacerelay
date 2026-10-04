<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Browser;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoPageActions;
use SurfaceRelay\Laravel\Tests\Support\FilamentOrderDemoHarness;

/**
 * Serves the T-807a order demo as a real Filament app under `testbench serve`.
 * Fixture-only: the trusted actor/tenant are fixed to tenant-a by the harness.
 */
final class OrderDemoBrowserServiceProvider extends ServiceProvider
{
    private const string RUNTIME_FILE_PATTERN = '[A-Za-z0-9._-]+\.js';

    public function register(): void
    {
        $this->app->register(OrderDemoPanelProvider::class);

        $this->app->singleton(
            FilamentOrderDemoHarness::class,
            static fn (Application $app): FilamentOrderDemoHarness => new FilamentOrderDemoHarness(
                app: $app,
                actorTenant: 'tenant-a',
                activeTenant: 'tenant-a',
            ),
        );

        $this->app->bind(
            OrderDemoPageActions::class,
            static fn (Application $app): OrderDemoPageActions => new OrderDemoPageActions(
                gateway: $app->make(FilamentOrderDemoHarness::class)->gateway,
                refundIdempotencyKey: 'order-demo-browser-refund-key',
            ),
        );
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            // The harness binds the trusted actor/tenant contexts that the
            // resource query and gateway resolve on every request.
            $this->app->make(FilamentOrderDemoHarness::class);
        }

        $this->app['view']->addLocation(dirname(__DIR__) . '/Fixtures/views');

        Route::get('/surfacerelay/order-demo.mjs', fn (): Response => $this->script(
            $this->exampleRoot() . '/client.mjs',
        ));

        Route::get('/surfacerelay/runtime/{file}', fn (string $file): Response => $this->script(
            $this->exampleRoot() . '/.tmp/runtime/' . $file,
        ))->where('file', self::RUNTIME_FILE_PATTERN);

        Route::get('/__test/orders', static fn (): JsonResponse => new JsonResponse(
            Order::query()->orderBy('id')->get(['id', 'tenant_id', 'status', 'held', 'refunded']),
        ));

        Route::post('/__test/reset', static function (): Response {
            OrderDemoBrowserSeeder::reset();

            return new Response('', 204);
        });
    }

    private function exampleRoot(): string
    {
        return dirname(__DIR__, 4) . '/examples/filament-orders-live';
    }

    private function script(string $path): Response
    {
        if (! is_file($path)) {
            abort(404);
        }

        return new Response((string) file_get_contents($path), 200, [
            'Content-Type' => 'text/javascript; charset=utf-8',
            'Cache-Control' => 'no-store',
        ]);
    }
}
