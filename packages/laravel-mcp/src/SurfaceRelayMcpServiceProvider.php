<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposureRegistry;

/**
 * Optional Laravel MCP bridge provider.
 *
 * The provider creates only the bridge-local explicit exposure registry.
 * Host applications remain responsible for binding the protocol-neutral
 * SurfaceRelay runtime (ActionRegistry / ActionBus / trusted resolvers) and
 * for choosing MCP routes, transports, authentication, and OAuth policy.
 */
final class SurfaceRelayMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            McpActionExposureRegistry::class,
            static fn (Application $app): McpActionExposureRegistry
                => new McpActionExposureRegistry(
                    $app->make(ActionRegistry::class),
                ),
        );
    }

    public function boot(): void
    {
        // Intentionally no route, transport, tool, or action auto-registration.
    }
}
