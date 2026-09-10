<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;

final class FilamentDependencyPolicyTest extends TestCase
{
    /** @throws JsonException */
    public function test_filament_is_dev_only_and_provider_remains_framework_neutral(): void
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(
            file_get_contents($root . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('filament/filament', $composer['require'] ?? []);
        self::assertSame('^5.0', $composer['require-dev']['filament/filament'] ?? null);

        $provider = file_get_contents($root . '/src/SurfaceRelayServiceProvider.php');
        self::assertIsString($provider);
        self::assertStringNotContainsString('Filament\\', $provider);
    }
}
