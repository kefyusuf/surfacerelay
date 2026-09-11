<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use RuntimeException;

final class OrderDemoTenantContext
{
    private ?string $tenantId = null;

    public function set(string $tenantId): void
    {
        if ($tenantId === '') {
            throw new RuntimeException('Order demo tenant must be non-empty.');
        }

        $this->tenantId = $tenantId;
    }

    public function current(): string
    {
        return $this->tenantId ?? throw new RuntimeException('Order demo tenant is not set.');
    }
}
