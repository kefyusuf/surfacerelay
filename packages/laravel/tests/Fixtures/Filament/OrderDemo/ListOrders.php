<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Filament\Resources\Pages\ListRecords;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

final class ListOrders extends ListRecords
{
    protected static string $resource = OrderResource::class;

    protected OrderDemoPageActions $orderDemoActions;

    public function boot(OrderDemoPageActions $actions): void
    {
        $this->orderDemoActions = $actions;
    }

    /** @return array{status: string}|array<string, mixed> */
    #[ExposeAction(id: 'orders.refund_selected', version: 1)]
    public function refundSelected(string $reason): array
    {
        return $this->orderDemoActions->refundSelected($this, $reason);
    }
}
