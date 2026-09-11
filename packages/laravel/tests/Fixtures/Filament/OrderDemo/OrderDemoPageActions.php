<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use RuntimeException;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;

final readonly class OrderDemoPageActions
{
    public function __construct(
        private FilamentActionGateway $gateway,
        private string $refundIdempotencyKey,
    ) {}

    /** @return array{orderId: int, held: true} */
    public function holdCurrent(EditOrder $page, string $reason): array
    {
        $outcome = $this->gateway->dispatch(
            page: $page,
            actionId: 'orders.hold_current',
            actionVersion: 1,
            input: ['reason' => $reason],
            surface: 'filament',
            correlationId: 'order-demo-livewire-hold',
            bindingId: 'order-demo-livewire-hold-binding',
        );

        if (!$outcome->completed) {
            throw new RuntimeException('Order demo hold action did not complete.');
        }

        /** @var array{orderId: int, held: true} */
        return $outcome->state->output;
    }

    /** @return array{status: string}|array<string, mixed> */
    public function refundSelected(ListOrders $page, string $reason): array
    {
        $outcome = $this->gateway->dispatch(
            page: $page,
            actionId: 'orders.refund_selected',
            actionVersion: 1,
            input: ['reason' => $reason],
            surface: 'filament',
            correlationId: 'order-demo-livewire-refund',
            bindingId: 'order-demo-livewire-refund-binding',
            idempotencyKey: $this->refundIdempotencyKey,
            contextExposure: FilamentContextExposure::activeFilters(),
        );

        if ($outcome->completed) {
            /** @var array<string, mixed> */
            return $outcome->state->output;
        }

        if ($outcome->halt?->code === 'confirmation_required') {
            return ['status' => 'confirmation_required'];
        }

        throw new RuntimeException('Order demo refund action did not reach confirmation.');
    }
}
