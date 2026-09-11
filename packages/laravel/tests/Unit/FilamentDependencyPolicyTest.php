<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use JsonException;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

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

    public function test_t503_keeps_active_filters_out_of_frozen_core_context_vocabulary(): void
    {
        $root = dirname(__DIR__, 2);
        $contextRequirement = file_get_contents($root . '/src/Enums/ContextRequirement.php');

        self::assertIsString($contextRequirement);
        self::assertStringNotContainsString('ActiveFilters', $contextRequirement);
        self::assertStringNotContainsString("'active_filters'", $contextRequirement);
    }

    public function test_t503_trusted_extension_primitives_remain_filament_free(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'src/Runtime/Context/TrustedContextExtension.php',
            'src/Runtime/Context/DuplicateTrustedContextExtension.php',
            'src/Runtime/Context/TrustedContextExtensionNotAvailable.php',
            'src/Runtime/InvocationContext.php',
            'src/Runtime/Scope/RuntimeScopeCanonicalizer.php',
            'src/Confirmation/ConfirmationScopeHasher.php',
            'src/Idempotency/IdempotencyIntentHasher.php',
        ] as $relativePath) {
            $source = file_get_contents($root . '/' . $relativePath);
            self::assertIsString($source, $relativePath);
            self::assertStringNotContainsString(
                'Filament\\',
                $source,
                $relativePath . ' must remain framework-neutral.',
            );
        }
    }

    public function test_t503_resolver_uses_public_applied_filter_state_not_pending_or_raw_state(): void
    {
        $root = dirname(__DIR__, 2);
        $resolver = file_get_contents(
            $root . '/src/Filament/Context/FilamentActiveFilterContextResolver.php',
        );

        self::assertIsString($resolver);
        self::assertStringContainsString('->getTable()->getFilters()', $resolver);
        self::assertStringContainsString('->getTableFilterState($filterName)', $resolver);

        foreach ([
            'tableDeferredFilters',
            'getTableFilterFormState(',
            '->tableFilters',
            'request(',
            'Route::',
        ] as $forbiddenAuthorityRead) {
            self::assertStringNotContainsString(
                $forbiddenAuthorityRead,
                $resolver,
                'Active-filter authority must not use pending/raw/request-derived state.',
            );
        }
    }

    public function test_t504_keeps_confirmation_idempotency_provider_and_livewire_core_filament_free(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'src/SurfaceRelayServiceProvider.php',
            'src/Confirmation/ConfirmationService.php',
            'src/Confirmation/ConfirmationStage.php',
            'src/Confirmation/ConfirmationScopeHasher.php',
            'src/Idempotency/IdempotencyIntentHasher.php',
        ] as $relativePath) {
            $source = file_get_contents($root . '/' . $relativePath);
            self::assertIsString($source, $relativePath);
            self::assertStringNotContainsString(
                'Filament\\',
                $source,
                $relativePath . ' must remain framework-neutral under T-504.',
            );
        }

        $livewireRoot = $root . '/src/Livewire';
        foreach (
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($livewireRoot))
            as $file
        ) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());
            self::assertIsString($source, $file->getPathname());
            self::assertStringNotContainsString(
                'Filament\\',
                $source,
                $file->getPathname() . ' must remain Filament-free under T-504.',
            );
        }
    }

    public function test_t504_confirmation_adapter_uses_locked_public_lifecycle_without_business_shortcuts(): void
    {
        $root = dirname(__DIR__, 2);
        $bridge = file_get_contents(
            $root . '/src/Filament/Confirmation/FilamentConfirmationBridge.php',
        );
        $trait = file_get_contents(
            $root . '/src/Filament/Confirmation/InteractsWithSurfaceRelayConfirmation.php',
        );

        self::assertIsString($bridge);
        self::assertIsString($trait);
        $source = $bridge . "\n" . $trait;

        self::assertStringContainsString('#[Locked]', $trait);
        self::assertStringContainsString('->mountAction(', $trait);
        self::assertStringContainsString('->getMountedAction()', $trait);
        self::assertStringContainsString('ConfirmationService::class', $trait);
        self::assertStringContainsString('approveChallenge($challengeId)', $trait);

        foreach ([
            '$mountedActions',
            'Reflection',
            'request(',
            'Route::',
            'FilamentActionGateway::dispatch',
            'ActionBus',
            'ActionCall',
        ] as $forbiddenShortcut) {
            self::assertStringNotContainsString(
                $forbiddenShortcut,
                $source,
                'T-504 confirmation UI must not own private framework state or business redispatch.',
            );
        }
    }
}
