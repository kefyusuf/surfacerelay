<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Smoke;

use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\TestCase;

final class T802CleanConsumerSmokeGreenTest extends TestCase
{
    public function test_built_artifact_runs_real_action_bus_smoke_in_clean_consumer(): void
    {
        if (PHP_VERSION_ID < 80400) {
            self::markTestSkipped('T-802 smoke GREEN is bounded to PHP 8.4 + Illuminate 13.');
        }

        $isIlluminate13 = InstalledVersions::satisfies(
            new VersionParser(),
            'illuminate/support',
            '^13.0',
        );
        if (!$isIlluminate13) {
            self::markTestSkipped('T-802 smoke GREEN is bounded to PHP 8.4 + Illuminate 13.');
        }

        $repoRoot = dirname(__DIR__, 4);
        $smoke = $repoRoot . '/scripts/fixtures/laravel-clean-consumer/smoke.php';
        self::assertFileExists($smoke);

        $source = file_get_contents($smoke);
        self::assertIsString($source);
        self::assertStringContainsString("vendor' . DIRECTORY_SEPARATOR . 'autoload.php", $source);
        self::assertStringNotContainsString('packages/laravel', $source);
        self::assertStringNotContainsString('../../packages', $source);

        $workRoot = sys_get_temp_dir() . '/surfacerelay-t802-green-' . bin2hex(random_bytes(6));
        $stageRoot = $workRoot . '/stage';
        $consumerRoot = $workRoot . '/consumer';
        $artifactVersion = '0.0.0-t802-step7.1';

        self::assertTrue(mkdir($workRoot, 0777, true));

        try {
            $revision = $this->runCommand([
                'git',
                '-C',
                $repoRoot,
                'rev-parse',
                'HEAD',
            ]);
            self::assertMatchesRegularExpression('/^[0-9a-f]{40}$/', trim($revision));

            $pythonBuild = <<<'PY'
from pathlib import Path
import sys

repo = Path(sys.argv[1])
sys.path.insert(0, str(repo))
from scripts import laravel_release_candidate as m
stage = Path(sys.argv[2])
version = sys.argv[3]
revision = sys.argv[4]
candidate = m.build_laravel_release_candidate(
    repo=repo,
    stage_root=stage,
    artifact_version=version,
    source_revision=revision,
)
m.validate_laravel_artifact_archive(
    archive_path=candidate["archivePath"],
    artifact_version=version,
)
m.create_clean_consumer_workspace(
    consumer_root=Path(sys.argv[5]),
    artifact_directory=Path(candidate["archivePath"]).parent,
    artifact_version=version,
    laravel_constraint="^13.0",
    package_source_root=repo / "packages" / "laravel",
)
PY;

            $this->runCommand([
                'python',
                '-c',
                $pythonBuild,
                $repoRoot,
                $stageRoot,
                $artifactVersion,
                trim($revision),
                $consumerRoot,
            ]);

            $this->runCommand([
                'composer',
                'update',
                '--working-dir=' . $consumerRoot,
                '--no-interaction',
                '--no-progress',
                '--prefer-dist',
                '--no-scripts',
            ]);

            $pythonVerify = <<<'PY'
from pathlib import Path
import sys

repo = Path(sys.argv[3])
sys.path.insert(0, str(repo))
from scripts import laravel_release_candidate as m

m.verify_clean_consumer_install(
    consumer_root=Path(sys.argv[1]),
    artifact_version=sys.argv[2],
    package_source_root=repo / "packages" / "laravel",
)
PY;

            $this->runCommand([
                'python',
                '-c',
                $pythonVerify,
                $consumerRoot,
                $artifactVersion,
                $repoRoot,
            ]);

            $output = $this->runCommand([
                PHP_BINARY,
                $smoke,
                $consumerRoot,
            ]);

            self::assertSame(
                "SurfaceRelay Laravel clean-consumer smoke: PASS\n",
                $output,
            );
        } finally {
            $this->removeTree($workRoot);
        }
    }

    /** @param list<string> $command */
    private function runCommand(array $command): string
    {
        $escaped = array_map(
            static fn (string $part): string => escapeshellarg($part),
            $command,
        );

        exec(implode(' ', $escaped) . ' 2>&1', $output, $exitCode);
        self::assertSame(
            0,
            $exitCode,
            implode(PHP_EOL, $output),
        );

        return implode(PHP_EOL, $output) . ($output === [] ? '' : "\n");
    }

    private function removeTree(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root,
                \FilesystemIterator::SKIP_DOTS,
            ),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $path = $item->getPathname();
            if ($item->isLink() || $item->isFile()) {
                @unlink($path);
                continue;
            }
            @rmdir($path);
        }

        @rmdir($root);
    }
}
