<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use JsonException;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\SurfaceRelayServiceProvider;

final class LaravelPackageProviderIntegrationTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    /** @throws JsonException */
    public function test_package_provider_is_auto_discoverable_and_loads_audit_migration(): void
    {
        self::assertTrue(
            class_exists(SurfaceRelayServiceProvider::class),
            'SurfaceRelayServiceProvider must exist so Laravel can install package migrations.',
        );

        $composerPath = dirname(__DIR__, 2) . '/composer.json';
        $composer = json_decode(
            file_get_contents($composerPath),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertSame(
            [SurfaceRelayServiceProvider::class],
            $composer['extra']['laravel']['providers'] ?? null,
            'Laravel package auto-discovery must register SurfaceRelayServiceProvider.',
        );

        $this->app->register(SurfaceRelayServiceProvider::class);

        $this->artisan('migrate', ['--force' => true])
            ->assertExitCode(0);

        self::assertTrue(
            $this->app['db']->connection('testing')->getSchemaBuilder()
                ->hasTable('surfacerelay_audit_events'),
            'The discovered package migration must create surfacerelay_audit_events.',
        );
    }
}
