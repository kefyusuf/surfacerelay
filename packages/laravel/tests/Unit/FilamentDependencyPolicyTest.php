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
}
