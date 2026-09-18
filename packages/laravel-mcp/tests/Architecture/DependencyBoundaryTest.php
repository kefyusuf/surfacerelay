<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class DependencyBoundaryTest extends TestCase
{
    public function test_base_laravel_package_has_no_mcp_dependency_or_source_import(): void
    {
        $packages = dirname(__DIR__, 3);
        $composerPath = $packages.'/laravel/composer.json';

        $contents = file_get_contents($composerPath);
        if ($contents === false) {
            throw new RuntimeException('Unable to read base Laravel composer.json.');
        }

        /** @var array<string, mixed> $baseComposer */
        $baseComposer = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('laravel/mcp', $baseComposer['require'] ?? []);
        self::assertArrayNotHasKey('laravel/mcp', $baseComposer['require-dev'] ?? []);

        $sourceRoot = $packages.'/laravel/src';
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $sourceRoot,
                RecursiveDirectoryIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            if ($source === false) {
                throw new RuntimeException('Unable to read '.$file->getPathname().'.');
            }

            self::assertDoesNotMatchRegularExpression(
                '/Laravel\\\\Mcp\\\\/i',
                $source,
                'Base Laravel runtime must remain MCP-independent: '.$file->getPathname(),
            );
        }
    }
}
