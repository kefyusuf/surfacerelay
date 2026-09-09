<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel;

use Illuminate\Support\ServiceProvider;

final class SurfaceRelayServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');
    }
}
