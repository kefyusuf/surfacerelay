<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionClass;
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Tests\Fixtures\PrepList\AddPrepListItem;
use SurfaceRelay\Laravel\Tests\Fixtures\PrepList\PrepListActionGateway;
use SurfaceRelay\Laravel\Tests\Fixtures\PrepList\PrepListComponent;
use SurfaceRelay\Laravel\Tests\Fixtures\PrepList\PrepListTestPipeline;

final class PrepListLivewireE2ETest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', '0123456789abcdef0123456789abcdef');
    }

    public function test_prep_list_fixture_types_exist(): void
    {
        self::assertTrue(class_exists(PrepListTestPipeline::class), 'PrepListTestPipeline must exist.');
        self::assertTrue(class_exists(PrepListComponent::class), 'PrepListComponent must exist.');
        self::assertTrue(class_exists(PrepListActionGateway::class), 'PrepListActionGateway must exist.');
        self::assertTrue(class_exists(AddPrepListItem::class), 'AddPrepListItem must exist.');
    }

    public function test_human_livewire_path_mutates_through_shared_action_bus_once(): void
    {
        $runtime = $this->runtime();

        Livewire::test(PrepListComponent::class)
            ->call('addItem', 'passport');

        self::assertSame([
            ['itemId' => 'item-1', 'name' => 'passport'],
        ], $runtime->store->all());
        self::assertSame(1, $runtime->addItem->calls);
        self::assertSame(1, $runtime->authorizer->calls);
        self::assertSame(['name' => 'passport'], $runtime->authorizer->lastInput);
        self::assertNotNull($runtime->authorizer->lastContext);
        self::assertTrue($runtime->authorizer->lastContext->has(ContextRequirement::BrowserSession));
        self::assertArrayNotHasKey('browser_session', $runtime->authorizer->lastInput);
    }

    public function test_binding_derived_agent_path_calls_exact_real_livewire_target(): void
    {
        $runtime = $this->runtime();
        $testable = Livewire::test(PrepListComponent::class);
        $instance = $testable->instance();

        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader($runtime->registry),
            new MethodLivewireComponentIdentityResolver(),
            new RandomBindingIdGenerator(),
        );
        $bindings = $producer->forComponent($instance);

        self::assertCount(1, $bindings);
        self::assertSame('prep_list.add_item', $bindings[0]->definition->id);
        self::assertSame(1, $bindings[0]->definition->version);
        self::assertSame($runtime->definition, $bindings[0]->definition);
        self::assertSame($instance->getId(), $bindings[0]->target['componentId']);
        self::assertSame('addItem', $bindings[0]->target['method']);

        $testable->call($bindings[0]->target['method'], 'passport');

        self::assertSame([
            ['itemId' => 'item-1', 'name' => 'passport'],
        ], $runtime->store->all());
        self::assertSame(1, $runtime->addItem->calls);
        self::assertSame(1, $runtime->authorizer->calls);
    }

    public function test_human_and_binding_derived_paths_produce_equivalent_state_from_fresh_runtime(): void
    {
        $human = $this->runtime();
        Livewire::test(PrepListComponent::class)->call('addItem', 'passport');
        $humanState = $human->store->all();

        $agent = $this->runtime();
        $testable = Livewire::test(PrepListComponent::class);
        $binding = (new LivewireBindingProducer(
            new LivewireActionExposureReader($agent->registry),
            new MethodLivewireComponentIdentityResolver(),
            new RandomBindingIdGenerator(),
        ))->forComponent($testable->instance())[0];
        $testable->call($binding->target['method'], 'passport');

        self::assertSame($humanState, $agent->store->all());
        self::assertSame(1, $human->addItem->calls);
        self::assertSame(1, $agent->addItem->calls);
    }

    public function test_invalid_input_halts_before_authorization_and_business_execution(): void
    {
        $runtime = $this->runtime();

        try {
            $runtime->gateway->addItem('');
            self::fail('Expected invalid input to halt the Prep List ActionBus path.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('input_validation_failed', $exception->getMessage());
        }

        self::assertSame([], $runtime->store->all());
        self::assertSame(0, $runtime->addItem->calls);
        self::assertSame(0, $runtime->authorizer->calls);
    }

    public function test_component_uses_boot_lifecycle_injection_not_own_constructor(): void
    {
        $this->assertFixtureTypesExist();
        $reflection = new ReflectionClass(PrepListComponent::class);
        $constructor = $reflection->getConstructor();
        $boot = $reflection->getMethod('boot');

        self::assertTrue(
            $constructor === null || $constructor->getDeclaringClass()->getName() !== PrepListComponent::class,
            'PrepListComponent must not own constructor injection.',
        );
        self::assertCount(1, $boot->getParameters());
        self::assertSame(PrepListActionGateway::class, $boot->getParameters()[0]->getType()?->getName());
    }

    private function runtime(): PrepListTestPipeline
    {
        $this->assertFixtureTypesExist();
        return PrepListTestPipeline::boot($this->app);
    }

    private function assertFixtureTypesExist(): void
    {
        self::assertTrue(class_exists(PrepListTestPipeline::class), 'PrepListTestPipeline must exist before this test can proceed.');
        self::assertTrue(class_exists(PrepListComponent::class), 'PrepListComponent must exist before this test can proceed.');
        self::assertTrue(class_exists(PrepListActionGateway::class), 'PrepListActionGateway must exist before this test can proceed.');
        self::assertTrue(class_exists(AddPrepListItem::class), 'AddPrepListItem must exist before this test can proceed.');
    }
}
