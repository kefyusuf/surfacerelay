<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Livewire\Binding\InvalidLivewireBindingProduction;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireMethodCallPlanBuilder;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposure;
use SurfaceRelay\Laravel\Livewire\LivewireBindingTarget;

final class LivewireMethodCallPlanBuilderTest extends TestCase
{
    public function test_required_and_optional_parameters_produce_deterministic_call_plan(): void
    {
        $definition = $this->definition(
            properties: ['name' => ['type' => 'string'], 'note' => ['type' => ['string', 'null']]],
            required: ['name'],
        );

        $plan = (new LivewireMethodCallPlanBuilder())->forExposure(
            new CompatibleCallPlanComponent(),
            new LivewireActionExposure($definition, 'update'),
        );

        self::assertSame(['name', 'note'], $plan->inputOrder);
        self::assertSame(1, $plan->requiredCount);
    }

    public function test_schema_property_order_is_not_invocation_order(): void
    {
        $definition = $this->definition(
            properties: ['note' => [], 'name' => []],
            required: ['name'],
        );

        $plan = (new LivewireMethodCallPlanBuilder())->forExposure(
            new CompatibleCallPlanComponent(),
            new LivewireActionExposure($definition, 'update'),
        );

        self::assertSame(['name', 'note'], $plan->inputOrder);
    }

    public function test_variadic_parameter_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('variadic');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new VariadicCallPlanComponent(),
            new LivewireActionExposure($this->definition(['values' => []], ['values']), 'perform'),
        );
    }

    public function test_by_reference_parameter_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('reference');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new ReferenceCallPlanComponent(),
            new LivewireActionExposure($this->definition(['value' => []], ['value']), 'perform'),
        );
    }

    public function test_method_level_dependency_parameter_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('dependency');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new DependencyCallPlanComponent(),
            new LivewireActionExposure($this->definition(['name' => []], ['name']), 'perform'),
        );
    }

    public function test_schema_property_set_must_match_method_parameters_exactly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('properties');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new CompatibleCallPlanComponent(),
            new LivewireActionExposure(
                $this->definition(['name' => [], 'extra' => []], ['name']),
                'update',
            ),
        );
    }

    public function test_schema_required_set_must_match_php_required_parameters(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('required');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new CompatibleCallPlanComponent(),
            new LivewireActionExposure(
                $this->definition(['name' => [], 'note' => []], ['name', 'note']),
                'update',
            ),
        );
    }

    public function test_schema_without_explicit_properties_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('properties');

        $definition = $this->definition([], [], ['type' => 'object']);

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new ZeroArgumentCallPlanComponent(),
            new LivewireActionExposure($definition, 'perform'),
        );
    }

    public function test_reserved_wire_method_name_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('reserved');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new ReservedMethodCallPlanComponent(),
            new LivewireActionExposure($this->definition([], []), 'set'),
        );
    }

    public function test_public_property_method_collision_fails_loudly(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('property');

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new PropertyCollisionCallPlanComponent(),
            new LivewireActionExposure($this->definition([], []), 'save'),
        );
    }

    public function test_output_schema_rejects_explicit_void_return(): void
    {
        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('output');

        $definition = $this->definition([], [], outputSchema: ['type' => 'object']);

        (new LivewireMethodCallPlanBuilder())->forExposure(
            new VoidOutputCallPlanComponent(),
            new LivewireActionExposure($definition, 'perform'),
        );
    }

    public function test_binding_target_validates_and_serializes_call_plan(): void
    {
        $target = new LivewireBindingTarget('component-1', 'update', ['name', 'note'], 1);

        self::assertSame([
            'componentId' => 'component-1',
            'method' => 'update',
            'inputOrder' => ['name', 'note'],
            'requiredCount' => 1,
        ], $target->toArray());
    }

    public function test_binding_target_rejects_duplicate_input_order(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LivewireBindingTarget('component-1', 'update', ['name', 'name'], 1);
    }

    public function test_binding_target_rejects_invalid_required_count(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LivewireBindingTarget('component-1', 'update', ['name'], 2);
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $required
     * @param array<string, mixed>|null $inputSchemaOverride
     * @param array<string, mixed>|null $outputSchema
     */
    private function definition(
        array $properties = [],
        array $required = [],
        ?array $inputSchemaOverride = null,
        ?array $outputSchema = null,
    ): ActionDefinition {
        $inputSchema = $inputSchemaOverride ?? [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
            'additionalProperties' => false,
        ];

        return new ActionDefinition(
            id: 'call_plan.perform',
            version: 1,
            title: 'Call plan action',
            description: 'Action used to verify deterministic Livewire browser call planning.',
            inputSchema: $inputSchema,
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: $outputSchema,
        );
    }
}

final class CompatibleCallPlanComponent
{
    public function update(string $name, ?string $note = null): array { return []; }
}

final class ZeroArgumentCallPlanComponent
{
    public function perform(): array { return []; }
}

final class VariadicCallPlanComponent
{
    public function perform(string ...$values): array { return []; }
}

final class ReferenceCallPlanComponent
{
    public function perform(string &$value): array { return []; }
}

final class DependencyCallPlanService {}

final class DependencyCallPlanComponent
{
    public function perform(DependencyCallPlanService $service, string $name): array { return []; }
}

final class ReservedMethodCallPlanComponent
{
    public function set(): array { return []; }
}

final class PropertyCollisionCallPlanComponent
{
    public string $save = 'state';
    public function save(): array { return []; }
}

final class VoidOutputCallPlanComponent
{
    public function perform(): void {}
}
