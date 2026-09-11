<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

final readonly class OrderDemoActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private OrderDemoActorContext $context) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        $actor = $this->context->current();

        if ($actor->getAuthIdentifier() === null) {
            return null;
        }

        return new ResolvedTrustedValue(
            value: $actor,
            provenance: new ContextProvenance('order_demo.actor'),
            confirmationScopeKey: 'order-demo-actor:' . (string) $actor->getAuthIdentifier(),
        );
    }
}
