<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Illuminate\Auth\GenericUser;
use RuntimeException;

final class OrderDemoActorContext
{
    private ?GenericUser $actor = null;

    public function set(GenericUser $actor): void
    {
        $this->actor = $actor;
    }

    public function current(): GenericUser
    {
        return $this->actor ?? throw new RuntimeException('Order demo actor is not set.');
    }
}
