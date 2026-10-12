<?php

declare(strict_types=1);

namespace App\Filament\Resources\A2Orders\Pages;

use App\A2\InspectOrder;
use App\Filament\Resources\A2Orders\A2OrderResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Locked;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

/** Server-side pilot only; no WebMCP lease or RuntimeBinding is issued yet. */
final class ListA2Orders extends ListRecords
{
    protected static string $resource = A2OrderResource::class;

    #[Locked]
    public string $inspectionStatus = 'idle';

    #[Locked]
    public ?array $inspected = null;

    #[ExposeAction('a2.orders.inspect', 1)]
    public function inspectOrder(int $orderId): void
    {
        $outcome = app(InspectOrder::class)->dispatch($this, ['order_id' => $orderId]);
        $this->inspectionStatus = $outcome->completed ? 'ok' : 'denied';
        $this->inspected = $outcome->completed ? $outcome->state->output : null;
    }
}
