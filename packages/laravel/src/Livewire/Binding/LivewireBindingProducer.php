<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Binding;

use SurfaceRelay\Laravel\Binding\BindingIdGenerator;
use SurfaceRelay\Laravel\Binding\RuntimeBinding;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\LivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Livewire\LivewireBindingTarget;
use SurfaceRelay\Laravel\Livewire\LivewireRuntimeBinding;

/**
 * Produces fresh component-scoped Runtime Bindings for one trusted mounted component.
 *
 * T-304 adds a server-issued positional call plan so bindings produced here are
 * executable through the documented Livewire browser API. Lower-level manual
 * LivewireBindingTarget construction may omit that plan, but this trusted
 * producer never does.
 */
final class LivewireBindingProducer
{
    private readonly LivewireMethodCallPlanBuilder $callPlanBuilder;

    public function __construct(
        private readonly LivewireActionExposureReader $exposureReader,
        private readonly LivewireComponentIdentityResolver $componentIdentityResolver,
        private readonly BindingIdGenerator $bindingIdGenerator,
        ?LivewireMethodCallPlanBuilder $callPlanBuilder = null,
    ) {
        $this->callPlanBuilder = $callPlanBuilder ?? new LivewireMethodCallPlanBuilder();
    }

    /** @return list<RuntimeBinding> */
    public function forComponent(object $component): array
    {
        $componentId = $this->componentIdentityResolver->resolve($component);
        $exposures = $this->exposureReader->forComponent($component);
        $bindings = [];
        /** @var array<string, true> $issuedIds */
        $issuedIds = [];

        foreach ($exposures as $exposure) {
            // Validate execution compatibility before consuming an issued binding ID.
            $callPlan = $this->callPlanBuilder->forExposure($component, $exposure);
            $bindingId = $this->bindingIdGenerator->generate();

            if (isset($issuedIds[$bindingId])) {
                throw InvalidLivewireBindingProduction::duplicateGeneratedBindingId($bindingId);
            }

            $issuedIds[$bindingId] = true;
            $bindings[] = LivewireRuntimeBinding::forComponent(
                bindingId: $bindingId,
                definition: $exposure->definition,
                target: new LivewireBindingTarget(
                    componentId: $componentId,
                    method: $exposure->method,
                    inputOrder: $callPlan->inputOrder,
                    requiredCount: $callPlan->requiredCount,
                ),
            );
        }

        return $bindings;
    }
}
