<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Binding\BindingIdGenerator;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;
use SurfaceRelay\Laravel\Livewire\Binding\InvalidLivewireBindingProduction;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

final class LivewireExecutableBindingProducerTest extends TestCase
{
    public function test_producer_emits_server_issued_positional_call_plan(): void
    {
        $definition = $this->definition(
            id: 'executable.update',
            properties: ['note' => [], 'name' => []],
            required: ['name'],
        );
        $producer = $this->producer($definition);

        $binding = $producer->forComponent(new ExecutableProducerComponent('component-1'))[0];

        self::assertSame([
            'componentId' => 'component-1',
            'method' => 'update',
            'inputOrder' => ['name', 'note'],
            'requiredCount' => 1,
        ], $binding->target);
    }

    public function test_producer_fails_before_issuing_binding_for_output_schema_void_method(): void
    {
        $definition = $this->definition(
            id: 'executable.void_output',
            properties: [],
            required: [],
            outputSchema: ['type' => 'object'],
        );
        $producer = $this->producer($definition);

        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('output');
        $producer->forComponent(new VoidOutputExecutableProducerComponent('component-1'));
    }

    public function test_producer_fails_before_issuing_binding_when_schema_cannot_map_method(): void
    {
        $definition = $this->definition(
            id: 'executable.mismatch',
            properties: ['other' => []],
            required: ['other'],
        );
        $producer = $this->producer($definition);

        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('properties');
        $producer->forComponent(new MismatchedExecutableProducerComponent('component-1'));
    }

    private function producer(ActionDefinition $definition): LivewireBindingProducer
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $generator = new class implements BindingIdGenerator {
            public function generate(): string { return 'binding-executable'; }
        };

        return new LivewireBindingProducer(
            new LivewireActionExposureReader($registry),
            new MethodLivewireComponentIdentityResolver(),
            $generator,
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @param list<string> $required
     * @param array<string, mixed>|null $outputSchema
     */
    private function definition(
        string $id,
        array $properties,
        array $required,
        ?array $outputSchema = null,
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: 1,
            title: 'Executable Livewire action',
            description: 'Action used to verify trusted browser-executable binding issuance.',
            inputSchema: [
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => false,
            ],
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

final class ExecutableProducerComponent
{
    public function __construct(private readonly string $id) {}
    public function getId(): string { return $this->id; }

    #[ExposeAction(id: 'executable.update', version: 1)]
    public function update(string $name, ?string $note = null): array { return []; }
}

final class VoidOutputExecutableProducerComponent
{
    public function __construct(private readonly string $id) {}
    public function getId(): string { return $this->id; }

    #[ExposeAction(id: 'executable.void_output', version: 1)]
    public function perform(): void {}
}

final class MismatchedExecutableProducerComponent
{
    public function __construct(private readonly string $id) {}
    public function getId(): string { return $this->id; }

    #[ExposeAction(id: 'executable.mismatch', version: 1)]
    public function perform(string $name): array { return []; }
}
