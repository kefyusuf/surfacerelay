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
 * This class issues descriptors only. It does not execute bindings, persist them,
 * inspect browser lifecycle, revoke old descriptors, or silently retarget them.
 */
final class LivewireBindingProducer
{
    public function __construct(
        private readonly LivewireActionExposureReader $exposureReader,
        private readonly LivewireComponentIdentityResolver $componentIdentityResolver,
        private readonly BindingIdGenerator $bindingIdGenerator,
    ) {}

    /** @return list<RuntimeBinding> */
    public function forComponent(object $component): array
    {
        $componentId = $this->componentIdentityResolver->resolve($component);
        $exposures = $this->exposureReader->forComponent($component);
        $bindings = [];
        /** @var array<string, true> $issuedIds */
        $issuedIds = [];

        foreach ($exposures as $exposure) {
            $bindingId = $this->bindingIdGenerator->generate();

            if (isset($issuedIds[$bindingId])) {
                throw InvalidLivewireBindingProduction::duplicateGeneratedBindingId($bindingId);
            }

            $issuedIds[$bindingId] = true;
            $bindings[] = LivewireRuntimeBinding::forComponent(
                bindingId: $bindingId,
                definition: $exposure->definition,
                target: new LivewireBindingTarget($componentId, $exposure->method),
            );
        }

        return $bindings;
    }
}
