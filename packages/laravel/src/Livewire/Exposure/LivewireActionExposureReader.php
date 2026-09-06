<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Exposure;

use ReflectionObject;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;
use SurfaceRelay\Laravel\Registry\ActionDefinitionNotFound;

/**
 * Resolves only explicitly annotated methods on the concrete component class.
 *
 * The reader never invokes component methods, never issues Runtime Bindings,
 * and never treats exposure as discovery or invocation authorization.
 */
final class LivewireActionExposureReader
{
    public function __construct(
        private readonly ActionRegistry $registry,
    ) {}

    /** @return list<LivewireActionExposure> */
    public function forComponent(object $component): array
    {
        $reflection = new ReflectionObject($component);
        $concreteClass = $reflection->getName();
        $exposures = [];
        /** @var array<string, string> $methodsByIdentity */
        $methodsByIdentity = [];

        foreach ($reflection->getMethods() as $method) {
            $attributes = $method->getAttributes(ExposeAction::class);
            if ($attributes === []) {
                continue;
            }

            // Exposure declared only by a parent is not inherited as
            // SurfaceRelay authority by a child component.
            if ($method->getDeclaringClass()->getName() !== $concreteClass) {
                continue;
            }

            if (count($attributes) !== 1) {
                throw InvalidLivewireActionExposure::duplicateAttribute($method->getName());
            }

            if (!$method->isPublic()) {
                throw InvalidLivewireActionExposure::methodNotPublic($method->getName());
            }

            if ($method->isStatic()) {
                throw InvalidLivewireActionExposure::methodStatic($method->getName());
            }

            /** @var ExposeAction $attribute */
            $attribute = $attributes[0]->newInstance();

            try {
                $definition = $this->registry->get($attribute->id, $attribute->version);
            } catch (ActionDefinitionNotFound) {
                throw InvalidLivewireActionExposure::actionNotRegistered(
                    $method->getName(),
                    $attribute->id,
                    $attribute->version,
                );
            }

            $identityKey = $definition->id . '@' . $definition->version;

            if (isset($methodsByIdentity[$identityKey])) {
                throw InvalidLivewireActionExposure::duplicateActionIdentity(
                    $definition->id,
                    $definition->version,
                    $methodsByIdentity[$identityKey],
                    $method->getName(),
                );
            }

            $methodsByIdentity[$identityKey] = $method->getName();
            $exposures[] = new LivewireActionExposure($definition, $method->getName());
        }

        usort(
            $exposures,
            static fn (LivewireActionExposure $a, LivewireActionExposure $b): int
                => [$a->definition->id, $a->definition->version, $a->method]
                    <=> [$b->definition->id, $b->definition->version, $b->method],
        );

        return $exposures;
    }
}
