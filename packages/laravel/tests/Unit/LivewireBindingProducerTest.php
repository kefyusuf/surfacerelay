<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Binding\BindingIdGenerator;
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
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
use SurfaceRelay\Laravel\Livewire\Exposure\InvalidLivewireActionExposure;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\LivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Livewire\LivewireBindingTarget;
use SurfaceRelay\Laravel\Livewire\LivewireRuntimeBinding;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

final class LivewireBindingProducerTest extends TestCase
{
    public function test_t203_types_exist(): void
    {
        self::assertTrue(interface_exists(BindingIdGenerator::class), 'BindingIdGenerator must exist.');
        self::assertTrue(class_exists(RandomBindingIdGenerator::class), 'RandomBindingIdGenerator must exist.');
        self::assertTrue(interface_exists(LivewireComponentIdentityResolver::class), 'LivewireComponentIdentityResolver must exist.');
        self::assertTrue(class_exists(MethodLivewireComponentIdentityResolver::class), 'MethodLivewireComponentIdentityResolver must exist.');
        self::assertTrue(class_exists(InvalidLivewireBindingProduction::class), 'InvalidLivewireBindingProduction must exist.');
        self::assertTrue(class_exists(LivewireBindingProducer::class), 'LivewireBindingProducer must exist.');
    }

    public function test_random_generator_produces_fresh_runtime_binding_compatible_ids(): void
    {
        $this->assertTypesExist();
        $generator = new RandomBindingIdGenerator();

        $first = $generator->generate();
        $second = $generator->generate();

        self::assertNotSame('', $first);
        self::assertLessThanOrEqual(240, mb_strlen($first, 'UTF-8'));
        self::assertNotSame($first, $second);

        $binding = LivewireRuntimeBinding::forComponent(
            bindingId: $first,
            definition: $this->definition('producer.random', 1),
            target: new LivewireBindingTarget('component-random', 'perform'),
        );

        self::assertSame($first, $binding->bindingId);
    }

    public function test_method_identity_resolver_preserves_exact_component_id(): void
    {
        $this->assertTypesExist();
        $resolver = new MethodLivewireComponentIdentityResolver();

        self::assertSame('component-123', $resolver->resolve(new IdentityOnlyComponent('component-123')));
    }

    public function test_missing_get_id_fails_loudly(): void
    {
        $this->assertTypesExist();
        $resolver = new MethodLivewireComponentIdentityResolver();

        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('getId');
        $resolver->resolve(new MissingIdentityComponent());
    }

    public function test_empty_component_id_fails_loudly(): void
    {
        $this->assertTypesExist();
        $resolver = new MethodLivewireComponentIdentityResolver();

        $this->expectException(InvalidLivewireBindingProduction::class);
        $resolver->resolve(new EmptyIdentityComponent());
    }

    public function test_non_string_component_id_fails_loudly(): void
    {
        $this->assertTypesExist();
        $resolver = new MethodLivewireComponentIdentityResolver();

        $this->expectException(InvalidLivewireBindingProduction::class);
        $resolver->resolve(new NonStringIdentityComponent());
    }

    public function test_exact_exposures_become_component_scoped_livewire_bindings_in_exposure_order(): void
    {
        $alpha = $this->definition('alpha.producer', 1);
        $beta = $this->definition('beta.producer', 2);
        $producer = $this->producer(
            $this->queuedGenerator('binding-z', 'binding-a'),
            $alpha,
            $beta,
        );

        $bindings = $producer->forComponent(new ProducerComponent('component-A'));

        self::assertCount(2, $bindings);
        self::assertSame($alpha, $bindings[0]->definition);
        self::assertSame($beta, $bindings[1]->definition);
        self::assertSame(['binding-z', 'binding-a'], array_map(static fn ($binding): string => $binding->bindingId, $bindings));
        self::assertSame('livewire', $bindings[0]->driver);
        self::assertSame('component', $bindings[0]->lifecycle->value);
        self::assertSame(['componentId' => 'component-A', 'method' => 'alpha'], $bindings[0]->target);
        self::assertSame(['componentId' => 'component-A', 'method' => 'beta'], $bindings[1]->target);
        self::assertNull($bindings[0]->expiresAt);
        self::assertSame([], $bindings[0]->extensions);
    }

    public function test_producer_uses_injected_trusted_identity_resolver(): void
    {
        $this->assertTypesExist();
        $definition = $this->definition('alpha.producer', 1);
        $registry = $this->registry($definition);
        $resolver = new class implements LivewireComponentIdentityResolver {
            public function resolve(object $component): string
            {
                return 'trusted-resolver-id';
            }
        };
        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader($registry),
            $resolver,
            $this->queuedGenerator('binding-1'),
        );

        $bindings = $producer->forComponent(new ProducerComponent('component-object-id'));

        self::assertSame('trusted-resolver-id', $bindings[0]->target['componentId']);
    }

    public function test_repeated_production_for_same_component_issues_fresh_binding_ids(): void
    {
        $definition = $this->definition('single.producer', 1);
        $producer = $this->producer(
            $this->queuedGenerator('binding-1', 'binding-2'),
            $definition,
        );
        $component = new SingleProducerComponent('component-A');

        $first = $producer->forComponent($component);
        $second = $producer->forComponent($component);

        self::assertSame('binding-1', $first[0]->bindingId);
        self::assertSame('binding-2', $second[0]->bindingId);
        self::assertSame($first[0]->target, $second[0]->target);
    }

    public function test_replacement_component_never_retargets_old_binding(): void
    {
        $definition = $this->definition('single.producer', 1);
        $producer = $this->producer(
            $this->queuedGenerator('binding-old', 'binding-new'),
            $definition,
        );

        $old = $producer->forComponent(new SingleProducerComponent('component-old'))[0];
        $new = $producer->forComponent(new SingleProducerComponent('component-new'))[0];

        self::assertSame('component-old', $old->target['componentId']);
        self::assertSame('component-new', $new->target['componentId']);
        self::assertSame('binding-old', $old->bindingId);
        self::assertSame('binding-new', $new->bindingId);
        self::assertSame('component-old', $old->target['componentId']);
    }

    public function test_empty_exposure_list_returns_empty_without_generating_ids(): void
    {
        $this->assertTypesExist();
        $generator = new class implements BindingIdGenerator {
            public function generate(): string
            {
                throw new \RuntimeException('Generator must not be called for an empty exposure list.');
            }
        };
        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader(new InMemoryActionRegistry()),
            new MethodLivewireComponentIdentityResolver(),
            $generator,
        );

        self::assertSame([], $producer->forComponent(new EmptyProducerComponent('component-empty')));
    }

    public function test_duplicate_generated_binding_ids_fail_loudly(): void
    {
        $producer = $this->producer(
            $this->queuedGenerator('duplicate-id', 'duplicate-id'),
            $this->definition('alpha.producer', 1),
            $this->definition('beta.producer', 2),
        );

        $this->expectException(InvalidLivewireBindingProduction::class);
        $this->expectExceptionMessage('duplicate-id');
        $producer->forComponent(new ProducerComponent('component-A'));
    }

    public function test_exposure_reader_errors_propagate_fail_loud(): void
    {
        $this->assertTypesExist();
        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader(new InMemoryActionRegistry()),
            new MethodLivewireComponentIdentityResolver(),
            $this->queuedGenerator('binding-1'),
        );

        $this->expectException(InvalidLivewireActionExposure::class);
        $producer->forComponent(new MissingRegisteredExposureComponent('component-A'));
    }

    public function test_producer_never_invokes_exposed_component_methods(): void
    {
        $component = new NeverInvokeProducerComponent('component-A');
        $producer = $this->producer(
            $this->queuedGenerator('binding-1'),
            $this->definition('never.invoke', 1),
        );

        $bindings = $producer->forComponent($component);

        self::assertCount(1, $bindings);
        self::assertSame(0, $component->calls);
        self::assertSame(1, $component->identityCalls);
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(interface_exists(BindingIdGenerator::class), 'BindingIdGenerator must exist.');
        self::assertTrue(class_exists(RandomBindingIdGenerator::class), 'RandomBindingIdGenerator must exist.');
        self::assertTrue(interface_exists(LivewireComponentIdentityResolver::class), 'LivewireComponentIdentityResolver must exist.');
        self::assertTrue(class_exists(MethodLivewireComponentIdentityResolver::class), 'MethodLivewireComponentIdentityResolver must exist.');
        self::assertTrue(class_exists(InvalidLivewireBindingProduction::class), 'InvalidLivewireBindingProduction must exist.');
        self::assertTrue(class_exists(LivewireBindingProducer::class), 'LivewireBindingProducer must exist.');
    }

    private function producer(BindingIdGenerator $generator, ActionDefinition ...$definitions): LivewireBindingProducer
    {
        $this->assertTypesExist();

        return new LivewireBindingProducer(
            new LivewireActionExposureReader($this->registry(...$definitions)),
            new MethodLivewireComponentIdentityResolver(),
            $generator,
        );
    }

    private function queuedGenerator(string ...$ids): BindingIdGenerator
    {
        $this->assertTypesExist();

        return new class($ids) implements BindingIdGenerator {
            private int $index = 0;

            /** @param list<string> $ids */
            public function __construct(private readonly array $ids) {}

            public function generate(): string
            {
                if (!array_key_exists($this->index, $this->ids)) {
                    throw new \RuntimeException('No queued binding ID remains.');
                }

                return $this->ids[$this->index++];
            }
        };
    }

    private function registry(ActionDefinition ...$definitions): InMemoryActionRegistry
    {
        $registry = new InMemoryActionRegistry();
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        return $registry;
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Binding producer action',
            description: 'Action Definition used by T-203 mounted binding producer tests.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}

final class IdentityOnlyComponent
{
    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}

final class MissingIdentityComponent {}

final class EmptyIdentityComponent
{
    public function getId(): string
    {
        return '';
    }
}

final class NonStringIdentityComponent
{
    public function getId(): mixed
    {
        return 123;
    }
}

final class ProducerComponent
{
    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        return $this->id;
    }

    #[ExposeAction(id: 'beta.producer', version: 2)]
    public function beta(): void {}

    #[ExposeAction(id: 'alpha.producer', version: 1)]
    public function alpha(): void {}
}

final class SingleProducerComponent
{
    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        return $this->id;
    }

    #[ExposeAction(id: 'single.producer', version: 1)]
    public function perform(): void {}
}

final class EmptyProducerComponent
{
    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        return $this->id;
    }
}

final class MissingRegisteredExposureComponent
{
    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        return $this->id;
    }

    #[ExposeAction(id: 'missing.producer', version: 1)]
    public function perform(): void {}
}

final class NeverInvokeProducerComponent
{
    public int $calls = 0;
    public int $identityCalls = 0;

    public function __construct(private readonly string $id) {}

    public function getId(): string
    {
        ++$this->identityCalls;
        return $this->id;
    }

    #[ExposeAction(id: 'never.invoke', version: 1)]
    public function dangerous(): void
    {
        ++$this->calls;
        throw new \RuntimeException('Producer must never invoke exposed methods.');
    }
}
