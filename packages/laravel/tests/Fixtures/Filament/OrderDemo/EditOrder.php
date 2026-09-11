<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

final class EditOrder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = OrderResource::class;

    protected string $view = 'filament-order-demo-page';

    protected OrderDemoPageActions $orderDemoActions;

    public function boot(OrderDemoPageActions $actions): void
    {
        $this->orderDemoActions = $actions;
    }

    public function mount(int | string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    /** @return array{orderId: int, held: true} */
    #[ExposeAction(id: 'orders.hold_current', version: 1)]
    public function holdCurrent(string $reason): array
    {
        return $this->orderDemoActions->holdCurrent($this, $reason);
    }
}
