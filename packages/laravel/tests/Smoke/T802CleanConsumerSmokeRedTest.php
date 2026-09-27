<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Smoke;

use Composer\InstalledVersions;
use PHPUnit\Framework\TestCase;

final class T802CleanConsumerSmokeRedTest extends TestCase
{
    public function test_clean_consumer_smoke_requires_artifact_installed_runtime(): void
    {
        $illuminate = InstalledVersions::getPrettyVersion('illuminate/support') ?? '';
        if (PHP_VERSION_ID < 80400 || preg_match('/^v?13\\./', $illuminate) !== 1) {
            self::markTestSkipped('T-802 smoke RED is bounded to PHP 8.4 + Illuminate 13.');
        }

        $repoRoot = dirname(__DIR__, 4);
        $smoke = $repoRoot . '/scripts/fixtures/laravel-clean-consumer/smoke.php';
        self::assertFileExists($smoke);

        $source = file_get_contents($smoke);
        self::assertIsString($source);
        self::assertStringContainsString("vendor' . DIRECTORY_SEPARATOR . 'autoload.php", $source);
        self::assertStringNotContainsString('packages/laravel', $source);
        self::assertStringNotContainsString('../../packages', $source);

        $consumer = sys_get_temp_dir() . '/surfacerelay-t802-red-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($consumer, 0777, true));

        try {
            $command = sprintf(
                '%s %s %s 2>&1',
                escapeshellarg(PHP_BINARY),
                escapeshellarg($smoke),
                escapeshellarg($consumer),
            );
            exec($command, $output, $exitCode);

            self::assertSame(
                0,
                $exitCode,
                implode(PHP_EOL, $output),
            );
        } finally {
            @rmdir($consumer);
        }
    }
}
