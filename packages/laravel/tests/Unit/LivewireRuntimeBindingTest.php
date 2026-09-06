<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use SurfaceRelay\Laravel\Binding\BindingLifecycle;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Livewire\LivewireBindingTarget;
use SurfaceRelay\Laravel\Livewire\LivewireRuntimeBinding;

final class LivewireRuntimeBindingTest extends TestCase
{
    public function test_reference_binding_matches_existing_fixture_shape(): void
    {
        $this->assertLivewireBindingTypesExist();

        $binding = LivewireRuntimeBinding::forComponent(
            bindingId: 'prep-list:component:example-123:add-item',
            definition: $this->definition('prep_list.add_item', 1),
            target: new LivewireBindingTarget('example-123', 'addItem'),
        );

        self::assertSame([
            'bindingId' => 'prep-list:component:example-123:add-item',
            'action' => [
                'id' => 'prep_list.add_item',
                'version' => 1,
            ],
            'driver' => 'livewire',
            'lifecycle' => 'component',
            'target' => [
                'componentId' => 'example-123',
                'method' => 'addItem',
            ],
            'expiresAt' => null,
        ], $binding->toArray());
    }

    public function test_livewire_factory_locks_driver_and_component_lifecycle(): void
    {
        $this->assertLivewireBindingTypesExist();

        $method = new ReflectionMethod(LivewireRuntimeBinding::class, 'forComponent');
        $parameterNames = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertNotContains('driver', $parameterNames);
        self::assertNotContains('lifecycle', $parameterNames);

        $binding = LivewireRuntimeBinding::forComponent(
            bindingId: 'binding-1',
            definition: $this->definition('orders.refund', 1),
            target: new LivewireBindingTarget('cmp-1', 'refund'),
        );

        self::assertSame('livewire', $binding->driver);
        self::assertSame(BindingLifecycle::Component, $binding->lifecycle);
    }

    public function test_target_preserves_component_id_and_method_verbatim(): void
    {
        $this->assertLivewireBindingTypesExist();

        $target = new LivewireBindingTarget(' cmp-1 ', ' refund ');

        self::assertSame(' cmp-1 ', $target->componentId);
        self::assertSame(' refund ', $target->method);
        self::assertSame([
            'componentId' => ' cmp-1 ',
            'method' => ' refund ',
        ], $target->toArray());
        self::assertSame($target->toArray(), $target->jsonSerialize());
    }

    public function test_empty_component_id_is_rejected(): void
    {
        $this->assertLivewireBindingTypesExist();
        $this->expectException(\InvalidArgumentException::class);
        new LivewireBindingTarget('', 'addItem');
    }

    public function test_empty_method_is_rejected(): void
    {
        $this->assertLivewireBindingTypesExist();
        $this->expectException(\InvalidArgumentException::class);
        new LivewireBindingTarget('cmp-1', '');
    }

    public function test_exact_action_versions_remain_independent(): void
    {
        $this->assertLivewireBindingTypesExist();

        $v1 = LivewireRuntimeBinding::forComponent(
            bindingId: 'binding-v1',
            definition: $this->definition('orders.refund', 1),
            target: new LivewireBindingTarget('cmp-1', 'refund'),
        );
        $v2 = LivewireRuntimeBinding::forComponent(
            bindingId: 'binding-v2',
            definition: $this->definition('orders.refund', 2),
            target: new LivewireBindingTarget('cmp-1', 'refund'),
        );

        self::assertSame(1, $v1->toArray()['action']['version']);
        self::assertSame(2, $v2->toArray()['action']['version']);
    }

    public function test_expiry_and_extensions_flow_through_generic_binding_validation(): void
    {
        $this->assertLivewireBindingTypesExist();

        $binding = LivewireRuntimeBinding::forComponent(
            bindingId: 'binding-1',
            definition: $this->definition('orders.refund', 1),
            target: new LivewireBindingTarget('cmp-1', 'refund'),
            expiresAt: '2026-09-07T10:30:00Z',
            extensions: ['example/debug' => ['enabled' => true]],
        );

        self::assertSame('2026-09-07T10:30:00Z', $binding->expiresAt);
        self::assertSame(['example/debug' => ['enabled' => true]], $binding->extensions);
    }

    public function test_factory_does_not_mutate_action_definition(): void
    {
        $this->assertLivewireBindingTypesExist();

        $definition = $this->definition('orders.refund', 3);
        $before = [
            $definition->id,
            $definition->version,
            $definition->title,
            $definition->description,
            $definition->scope,
            $definition->effect,
        ];

        LivewireRuntimeBinding::forComponent(
            bindingId: 'binding-1',
            definition: $definition,
            target: new LivewireBindingTarget('cmp-1', 'refund'),
        );

        self::assertSame($before, [
            $definition->id,
            $definition->version,
            $definition->title,
            $definition->description,
            $definition->scope,
            $definition->effect,
        ]);
    }

    public function test_livewire_target_is_readonly(): void
    {
        $this->assertLivewireBindingTypesExist();
        self::assertTrue((new ReflectionClass(LivewireBindingTarget::class))->isReadOnly());
    }

    public function test_livewire_is_not_a_production_package_dependency(): void
    {
        $composer = json_decode(
            (string) file_get_contents(__DIR__ . '/../../composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertArrayNotHasKey('livewire/livewire', $composer['require'] ?? []);
        self::assertArrayHasKey('livewire/livewire', $composer['require-dev'] ?? []);
    }

    private function assertLivewireBindingTypesExist(): void
    {
        self::assertTrue(class_exists(LivewireBindingTarget::class), 'LivewireBindingTarget must exist before this test can proceed.');
        self::assertTrue(class_exists(LivewireRuntimeBinding::class), 'LivewireRuntimeBinding must exist before this test can proceed.');
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Example action',
            description: 'Executes application logic for a bound component action.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
