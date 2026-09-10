<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\DuplicateTrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtensionNotAvailable;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class TrustedContextExtensionTest extends TestCase
{
    public function test_namespaced_extension_preserves_exact_value_provenance_and_scope_key(): void
    {
        $extension = new TrustedContextExtension(
            'filament/active_filters',
            ['status' => ['value' => 'pending']],
            new ContextProvenance('filament.active_filters'),
            'scope-A',
        );

        self::assertSame('filament/active_filters', $extension->key);
        self::assertSame(['status' => ['value' => 'pending']], $extension->value);
        self::assertSame('filament.active_filters', $extension->provenance->provider);
        self::assertSame('scope-A', $extension->scopeKey);
    }

    public function test_falsy_non_null_values_are_valid_present_extension_values(): void
    {
        foreach ([[], false, 0, 0.0, ''] as $value) {
            $extension = new TrustedContextExtension(
                'test/falsy',
                $value,
                new ContextProvenance('test.extension'),
            );

            self::assertSame($value, $extension->value);
        }
    }

    public function test_invalid_non_namespaced_extension_keys_are_rejected(): void
    {
        foreach (['active_filters', '/active_filters', 'Filament/active_filters', 'filament/active filters'] as $key) {
            try {
                new TrustedContextExtension($key, [], new ContextProvenance('test.extension'));
                self::fail('Expected invalid trusted extension key to be rejected.');
            } catch (InvalidArgumentException $e) {
                self::assertSame('Trusted context extension key must be a valid namespaced identifier.', $e->getMessage());
            }
        }
    }

    public function test_null_extension_value_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trusted context extension value must not be null.');

        new TrustedContextExtension(
            'filament/active_filters',
            null,
            new ContextProvenance('filament.active_filters'),
        );
    }

    public function test_empty_scope_key_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Trusted context extension scopeKey must be null or a non-empty string.');

        new TrustedContextExtension(
            'filament/active_filters',
            [],
            new ContextProvenance('filament.active_filters'),
            '',
        );
    }

    public function test_invocation_context_looks_up_extensions_independently_from_metadata(): void
    {
        $extension = $this->extension('filament/active_filters', ['status' => ['value' => 'pending']]);
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            metadata: [
                'filament/active_filters' => ['status' => ['value' => 'spoofed']],
                'other/extension' => ['spoofed' => true],
            ],
            trustedExtensions: [$extension],
        );

        self::assertTrue($context->hasTrustedExtension('filament/active_filters'));
        self::assertFalse($context->hasTrustedExtension('other/extension'));
        self::assertSame($extension, $context->getTrustedExtension('filament/active_filters'));
        self::assertSame($extension, $context->requireTrustedExtension('filament/active_filters'));
    }

    public function test_missing_required_extension_fails_closed(): void
    {
        $context = new InvocationContext('filament', 'corr-1');

        $this->expectException(TrustedContextExtensionNotAvailable::class);
        $context->requireTrustedExtension('filament/active_filters');
    }

    public function test_duplicate_extension_keys_are_rejected(): void
    {
        $this->expectException(DuplicateTrustedContextExtension::class);

        new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            trustedExtensions: [
                $this->extension('filament/active_filters', []),
                $this->extension('filament/active_filters', ['status' => ['value' => 'pending']]),
            ],
        );
    }

    public function test_extensions_are_returned_in_exact_key_byte_order(): void
    {
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            trustedExtensions: [
                $this->extension('zeta/state', []),
                $this->extension('alpha/state', []),
                $this->extension('filament/active_filters', []),
            ],
        );

        self::assertSame(
            ['alpha/state', 'filament/active_filters', 'zeta/state'],
            array_map(
                static fn (TrustedContextExtension $entry): string => $entry->key,
                $context->allTrustedExtensions(),
            ),
        );
    }

    public function test_with_trusted_entry_preserves_trusted_extensions(): void
    {
        $extension = $this->extension('filament/active_filters', []);
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            trustedExtensions: [$extension],
        );

        $updated = $context->withTrustedEntry(new TrustedContextEntry(
            ContextRequirement::Tenant,
            'tenant-A',
            new ContextProvenance('test.tenant'),
        ));

        self::assertSame([$extension], $updated->allTrustedExtensions());
        self::assertSame('tenant-A', $updated->require(ContextRequirement::Tenant)->value);
    }

    public function test_with_trusted_extension_preserves_core_entries(): void
    {
        $tenant = new TrustedContextEntry(
            ContextRequirement::Tenant,
            'tenant-A',
            new ContextProvenance('test.tenant'),
        );
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            trustedContext: [$tenant],
        );

        $extension = $this->extension('filament/active_filters', []);
        $updated = $context->withTrustedExtension($extension);

        self::assertSame([$tenant], $updated->allTrusted());
        self::assertSame([$extension], $updated->allTrustedExtensions());
    }

    public function test_non_extension_values_are_rejected_from_trusted_extension_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('trustedExtensions must only contain TrustedContextExtension instances.');

        new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-1',
            trustedExtensions: ['filament/active_filters' => ['spoofed' => true]],
        );
    }

    private function extension(string $key, mixed $value): TrustedContextExtension
    {
        return new TrustedContextExtension(
            $key,
            $value,
            new ContextProvenance('test.extension'),
            'scope:' . $key,
        );
    }
}
