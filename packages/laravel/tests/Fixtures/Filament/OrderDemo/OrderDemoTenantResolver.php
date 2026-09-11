<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

final readonly class OrderDemoTenantResolver implements TenantResolver
{
    public function __construct(private OrderDemoTenantContext $context) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        $tenantId = $this->context->current();

        return new ResolvedTrustedValue(
            value: $tenantId,
            provenance: new ContextProvenance('order_demo.tenant'),
            confirmationScopeKey: 'order-demo-tenant:' . $tenantId,
        );
    }
}
