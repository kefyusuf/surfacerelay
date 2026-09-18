<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp;

use Illuminate\Support\ServiceProvider;

/**
 * Optional Laravel MCP bridge provider.
 *
 * T-703 Task 1 intentionally registers no actions, routes, transports,
 * or MCP tools. Later tasks add only the explicitly designed bridge wiring.
 */
final class SurfaceRelayMcpServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
