<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Filament\Resources\Pages\ListRecords;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

final class ListOrders extends ListRecords
{
    use InteractsWithSurfaceRelayConfirmation;

    protected static string $resource = OrderResource::class;

    protected OrderDemoPageActions $orderDemoActions;

    public function boot(OrderDemoPageActions $actions): void
    {
        $this->orderDemoActions = $actions;
    }

    /**
     * The approved receipt, if any, comes from this page's server-side state
     * (D-076), never from the caller.
     *
     * @return array{status: string}|array<string, mixed>
     */
    #[ExposeAction(id: 'orders.refund_selected', version: 1)]
    public function refundSelected(string $reason): array
    {
        return $this->orderDemoActions->refundSelected(
            $this,
            $reason,
            $this->pullApprovedSurfaceRelayConfirmationReceipt(),
        );
    }
}
