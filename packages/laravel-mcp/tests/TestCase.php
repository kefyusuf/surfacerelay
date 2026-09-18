<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests;

use Laravel\Mcp\Server\McpServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use SurfaceRelay\Laravel\SurfaceRelayServiceProvider;
use SurfaceRelay\LaravelMcp\SurfaceRelayMcpServiceProvider;

abstract class TestCase extends Orchestra
{
    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            SurfaceRelayServiceProvider::class,
            McpServiceProvider::class,
            SurfaceRelayMcpServiceProvider::class,
        ];
    }
}
