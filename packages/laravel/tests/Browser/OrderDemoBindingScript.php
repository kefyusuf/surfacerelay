<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Browser;

use Livewire\Livewire;
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
use SurfaceRelay\Laravel\Binding\RuntimeBinding;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\EditOrder;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\ListOrders;
use SurfaceRelay\Laravel\Tests\Support\FilamentOrderDemoHarness;

/**
 * Renders the exposed Action bindings of the Livewire page being rendered as
 * inert JSON inside that component's own DOM. Bindings come only from the
 * production LivewireBindingProducer.
 */
final readonly class OrderDemoBindingScript
{
    public function __construct(private FilamentOrderDemoHarness $harness) {}

    public function render(): string
    {
        $component = Livewire::current();
        if (! $component instanceof ListOrders && ! $component instanceof EditOrder) {
            return '';
        }

        $producer = new LivewireBindingProducer(
            new LivewireActionExposureReader($this->harness->registry),
            new MethodLivewireComponentIdentityResolver(),
            new RandomBindingIdGenerator(),
        );

        $tools = array_map(
            static fn (RuntimeBinding $binding): array => [
                'definition' => self::definition($binding->definition),
                'binding' => $binding->toArray(),
            ],
            $producer->forComponent($component),
        );

        return '<script type="application/json" data-surfacerelay-bindings>'
            . json_encode($tools, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES)
            . '</script>';
    }

    /** @return array<string, mixed> */
    private static function definition(ActionDefinition $definition): array
    {
        return [
            'id' => $definition->id,
            'version' => $definition->version,
            'title' => $definition->title,
            'description' => $definition->description,
            'inputSchema' => $definition->inputSchema,
            'scope' => $definition->scope->value,
            'effect' => $definition->effect->value,
            'risk' => $definition->risk->value,
            'idempotency' => $definition->idempotency->value,
            'outputSensitivity' => $definition->outputSensitivity->value,
            'outputContentTrust' => $definition->outputContentTrust->value,
            'contextRequirements' => array_map(
                static fn (ContextRequirement $requirement): string => $requirement->value,
                $definition->contextRequirements,
            ),
        ];
    }
}
