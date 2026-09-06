<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SurfaceRelay\Laravel\Binding\BindingLifecycle;
use SurfaceRelay\Laravel\Binding\InvalidRuntimeBinding;
use SurfaceRelay\Laravel\Binding\RuntimeBinding;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;

final class RuntimeBindingTest extends TestCase
{
    public function test_serializes_exact_action_identity_and_binding_shape(): void
    {
        $this->assertRuntimeBindingTypesExist();

        $binding = new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(version: 2),
            driver: 'custom.driver:v1',
            lifecycle: BindingLifecycle::Page,
            target: ['handle' => 'x'],
            expiresAt: '2026-09-07T10:00:00Z',
            extensions: ['example/cache' => ['ttl' => 30]],
        );

        self::assertSame([
            'bindingId' => 'binding-1',
            'action' => [
                'id' => 'orders.refund',
                'version' => 2,
            ],
            'driver' => 'custom.driver:v1',
            'lifecycle' => 'page',
            'target' => ['handle' => 'x'],
            'expiresAt' => '2026-09-07T10:00:00Z',
            'extensions' => ['example/cache' => ['ttl' => 30]],
        ], $binding->toArray());
        self::assertSame($binding->toArray(), $binding->jsonSerialize());
    }

    public function test_null_expiry_is_serialized_and_empty_extensions_are_omitted(): void
    {
        $this->assertRuntimeBindingTypesExist();

        $binding = new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
        );

        self::assertSame([
            'bindingId' => 'binding-1',
            'action' => ['id' => 'orders.refund', 'version' => 1],
            'driver' => 'livewire',
            'lifecycle' => 'component',
            'target' => ['componentId' => 'cmp-1'],
            'expiresAt' => null,
        ], $binding->toArray());
    }

    public function test_binding_lifecycle_values_match_the_frozen_contract_exactly(): void
    {
        $this->assertRuntimeBindingTypesExist();

        self::assertSame(
            ['page', 'component', 'session', 'persistent'],
            array_map(
                static fn (BindingLifecycle $lifecycle): string => $lifecycle->value,
                BindingLifecycle::cases(),
            ),
        );
    }

    #[DataProvider('invalidBindingIds')]
    public function test_invalid_binding_ids_are_rejected(string $bindingId): void
    {
        $this->assertRuntimeBindingTypesExist();
        $this->expectException(InvalidRuntimeBinding::class);

        new RuntimeBinding(
            bindingId: $bindingId,
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
        );
    }

    public static function invalidBindingIds(): iterable
    {
        yield 'empty' => [''];
        yield 'overlength' => [str_repeat('a', 241)];
    }

    #[DataProvider('invalidDrivers')]
    public function test_invalid_driver_identifiers_are_rejected(string $driver): void
    {
        $this->assertRuntimeBindingTypesExist();
        $this->expectException(InvalidRuntimeBinding::class);

        new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: $driver,
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
        );
    }

    public static function invalidDrivers(): iterable
    {
        yield 'empty' => [''];
        yield 'uppercase' => ['Livewire'];
        yield 'space' => ['live wire'];
        yield 'too long' => ['a' . str_repeat('b', 80)];
    }

    public function test_empty_target_is_rejected(): void
    {
        $this->assertRuntimeBindingTypesExist();
        $this->expectException(InvalidRuntimeBinding::class);

        new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: [],
        );
    }

    #[DataProvider('validExpiryValues')]
    public function test_valid_rfc3339_expiry_values_are_preserved_verbatim(string $expiresAt): void
    {
        $this->assertRuntimeBindingTypesExist();

        $binding = new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
            expiresAt: $expiresAt,
        );

        self::assertSame($expiresAt, $binding->expiresAt);
        self::assertSame($expiresAt, $binding->toArray()['expiresAt']);
    }

    public static function validExpiryValues(): iterable
    {
        yield 'UTC' => ['2026-09-07T10:30:00Z'];
        yield 'offset' => ['2026-09-07T13:30:00+03:00'];
        yield 'fraction' => ['2026-09-07T10:30:00.123456Z'];
    }

    #[DataProvider('invalidExpiryValues')]
    public function test_invalid_expiry_values_are_rejected(string $expiresAt): void
    {
        $this->assertRuntimeBindingTypesExist();
        $this->expectException(InvalidRuntimeBinding::class);

        new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
            expiresAt: $expiresAt,
        );
    }

    public static function invalidExpiryValues(): iterable
    {
        yield 'natural language' => ['tomorrow afternoon'];
        yield 'missing timezone' => ['2026-09-07T10:30:00'];
        yield 'impossible date' => ['2026-13-40T10:30:00Z'];
    }

    #[DataProvider('invalidExtensionKeys')]
    public function test_invalid_extension_keys_are_rejected(string $key): void
    {
        $this->assertRuntimeBindingTypesExist();
        $this->expectException(InvalidRuntimeBinding::class);

        new RuntimeBinding(
            bindingId: 'binding-1',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
            extensions: [$key => true],
        );
    }

    public static function invalidExtensionKeys(): iterable
    {
        yield 'not namespaced' => ['cache'];
        yield 'empty namespace' => ['/cache'];
        yield 'empty name' => ['example/'];
        yield 'uppercase namespace' => ['Example/cache'];
    }

    public function test_runtime_binding_is_readonly(): void
    {
        $this->assertRuntimeBindingTypesExist();
        self::assertTrue((new ReflectionClass(RuntimeBinding::class))->isReadOnly());
    }

    private function assertRuntimeBindingTypesExist(): void
    {
        self::assertTrue(enum_exists(BindingLifecycle::class), 'BindingLifecycle must exist before this test can proceed.');
        self::assertTrue(class_exists(InvalidRuntimeBinding::class), 'InvalidRuntimeBinding must exist before this test can proceed.');
        self::assertTrue(class_exists(RuntimeBinding::class), 'RuntimeBinding must exist before this test can proceed.');
    }

    private function definition(int $version = 1): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: $version,
            title: 'Refund order',
            description: 'Refunds an order through application logic.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
