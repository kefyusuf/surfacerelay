<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo;

use RuntimeException;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class OrderDemoExecutor implements ActionExecutor
{
    public int $holdExecutions = 0;

    public int $refundExecutions = 0;

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        return match ($definition->id) {
            'orders.hold_current' => $this->holdCurrent($context),
            'orders.refund_selected' => $this->refundSelected($context),
            default => throw new RuntimeException('Unsupported order demo action.'),
        };
    }

    /** @return array{orderId: int, held: true} */
    private function holdCurrent(InvocationContext $context): array
    {
        $order = $context->require(ContextRequirement::CurrentRecord)->value;
        if (!$order instanceof Order) {
            throw new RuntimeException('Order demo current record is invalid.');
        }

        $order->held = true;
        $order->save();
        $this->holdExecutions++;

        return [
            'orderId' => (int) $order->getKey(),
            'held' => true,
        ];
    }

    /** @return array{refundedCount: int, orderIds: list<int>} */
    private function refundSelected(InvocationContext $context): array
    {
        $orders = $context->require(ContextRequirement::CurrentSelection)->value;
        if (!is_array($orders)) {
            throw new RuntimeException('Order demo selection is invalid.');
        }

        $this->refundExecutions++;
        $orderIds = [];

        foreach ($orders as $order) {
            if (!$order instanceof Order) {
                throw new RuntimeException('Order demo selection is invalid.');
            }

            $order->refunded = true;
            $order->save();
            $orderIds[] = (int) $order->getKey();
        }

        return [
            'refundedCount' => count($orders),
            'orderIds' => $orderIds,
        ];
    }
}
