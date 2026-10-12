<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\A2\InspectOrder;
use App\Filament\Resources\A2Orders\Pages\ListA2Orders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use SurfaceRelay\Laravel\Binding\BindingLifecycle;
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use Tests\TestCase;

/** B1 only: a real mounted Filament page must match the published call-plan contract. */
final class A2BindingProductionTest extends TestCase
{
    use RefreshDatabase;

    /** A focused RED-on-baseline check for the method-input naming contract. */
    public function test_exposed_method_parameters_match_canonical_input_schema(): void
    {
        $definition = InspectOrder::definition();
        $method = new \ReflectionMethod(ListA2Orders::class, 'inspectOrder');
        $parameters = array_map(
            static fn (\ReflectionParameter $parameter): string => $parameter->getName(),
            $method->getParameters(),
        );

        self::assertSame(['order_id'], $parameters);
        self::assertSame($parameters, array_keys($definition->inputSchema['properties']));
        self::assertSame($parameters, $definition->inputSchema['required']);
    }

    public function test_mounted_page_issues_one_exact_livewire_component_binding(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $member = User::factory()->create();
        DB::table('a2_memberships')->insert([
            'user_id' => $member->getKey(),
            'tenant_id' => 'tenant-a',
            'can_inspect' => true,
        ]);

        $page = Livewire::actingAs($member)
            ->test(ListA2Orders::class)
            ->instance();

        $definition = InspectOrder::definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $bindings = (new LivewireBindingProducer(
            new LivewireActionExposureReader($registry),
            new MethodLivewireComponentIdentityResolver(),
            new RandomBindingIdGenerator(),
        ))->forComponent($page);

        self::assertCount(1, $bindings);
        $binding = $bindings[0];
        self::assertSame($definition, $binding->definition);
        self::assertSame('a2.orders.inspect', $binding->definition->id);
        self::assertSame(1, $binding->definition->version);
        self::assertMatchesRegularExpression('/^sr_[a-f0-9]{32}$/', $binding->bindingId);
        self::assertSame('livewire', $binding->driver);
        self::assertSame(BindingLifecycle::Component, $binding->lifecycle);
        self::assertSame([
            'componentId' => $page->getId(),
            'method' => 'inspectOrder',
            'inputOrder' => ['order_id'],
            'requiredCount' => 1,
        ], $binding->target);
        self::assertSame([
            'id' => 'a2.orders.inspect',
            'version' => 1,
        ], $binding->toArray()['action']);
        self::assertNull($binding->expiresAt);
    }
}
