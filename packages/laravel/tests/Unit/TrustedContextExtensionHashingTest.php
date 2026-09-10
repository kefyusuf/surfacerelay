<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\UnrepresentableConfirmationScope;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\UnrepresentableIdempotencyScope;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;

final class TrustedContextExtensionHashingTest extends TestCase
{
    public function test_runtime_scope_canonicalizer_uses_extension_scope_key_without_inspecting_value(): void
    {
        $entry = new TrustedContextExtension(
            'filament/active_filters',
            new TrustedExtensionOpaqueValue('must-not-be-inspected'),
            new ContextProvenance('filament.active_filters'),
            'filters-A',
        );

        self::assertSame(
            ['scopeKey' => 'filters-A'],
            (new RuntimeScopeCanonicalizer())->trustedExtensionIdentity(
                $entry,
                'extensions.filament/active_filters',
            ),
        );
    }

    public function test_changed_trusted_extension_changes_confirmation_and_idempotency_intent(): void
    {
        $stateA = $this->state($this->extension('filters-A'));
        $stateB = $this->state($this->extension('filters-B'));

        self::assertNotSame(
            (new ConfirmationScopeHasher())->fingerprint($stateA),
            (new ConfirmationScopeHasher())->fingerprint($stateB),
        );
        self::assertNotSame(
            (new IdempotencyIntentHasher())->fingerprint($stateA),
            (new IdempotencyIntentHasher())->fingerprint($stateB),
        );
    }

    public function test_identical_trusted_extension_scope_is_stable_and_metadata_imitation_has_no_effect(): void
    {
        $extension = $this->extension('filters-A');
        $stateA = $this->state($extension, ['locale' => 'tr-TR']);
        $stateB = $this->state($extension, [
            'locale' => 'en-US',
            'filament/active_filters' => ['status' => ['value' => 'spoofed']],
        ]);

        self::assertSame(
            (new ConfirmationScopeHasher())->fingerprint($stateA),
            (new ConfirmationScopeHasher())->fingerprint($stateB),
        );
        self::assertSame(
            (new IdempotencyIntentHasher())->fingerprint($stateA),
            (new IdempotencyIntentHasher())->fingerprint($stateB),
        );
    }

    public function test_unrepresentable_extension_without_scope_key_fails_closed_for_confirmation(): void
    {
        $state = $this->state(new TrustedContextExtension(
            'filament/active_filters',
            new TrustedExtensionOpaqueValue('SECRET_FILTER_VALUE'),
            new ContextProvenance('filament.active_filters'),
        ));

        $this->expectException(UnrepresentableConfirmationScope::class);
        $this->expectExceptionMessage('extensions.filament/active_filters');

        (new ConfirmationScopeHasher())->fingerprint($state);
    }

    public function test_unrepresentable_extension_without_scope_key_fails_closed_for_idempotency(): void
    {
        $state = $this->state(new TrustedContextExtension(
            'filament/active_filters',
            new TrustedExtensionOpaqueValue('SECRET_FILTER_VALUE'),
            new ContextProvenance('filament.active_filters'),
        ));

        try {
            (new IdempotencyIntentHasher())->fingerprint($state);
            self::fail('Unrepresentable trusted extension must fail idempotency intent hashing.');
        } catch (UnrepresentableIdempotencyScope $e) {
            self::assertSame('extensions.filament/active_filters', $e->path);
            self::assertStringNotContainsString('SECRET_FILTER_VALUE', $e->getMessage());
        }
    }

    private function extension(string $scopeKey): TrustedContextExtension
    {
        return new TrustedContextExtension(
            'filament/active_filters',
            ['status' => ['value' => 'pending']],
            new ContextProvenance('filament.active_filters'),
            $scopeKey,
        );
    }

    /** @param array<string, mixed> $metadata */
    private function state(TrustedContextExtension $extension, array $metadata = []): ActionPipelineState
    {
        return new ActionPipelineState(
            definition: new ActionDefinition(
                id: 'orders.filtered.operation',
                version: 1,
                title: 'Filtered operation',
                description: 'Operates against explicitly exposed filtered state.',
                inputSchema: ['type' => 'object'],
                scope: ActionScope::PageScoped,
                effect: ActionEffect::ReversibleWrite,
                risk: ActionRisk::High,
                idempotency: IdempotencyPolicy::RequiredKey,
                outputSensitivity: OutputSensitivity::Normal,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [],
            ),
            input: ['mode' => 'apply'],
            context: new InvocationContext(
                surface: 'filament',
                correlationId: 'corr-filter-hash',
                idempotencyKey: 'idem-filter-hash',
                metadata: $metadata,
                trustedExtensions: [$extension],
            ),
            bindingId: 'binding-filter-hash',
        );
    }
}

final class TrustedExtensionOpaqueValue
{
    public function __construct(public string $secret) {}
}
