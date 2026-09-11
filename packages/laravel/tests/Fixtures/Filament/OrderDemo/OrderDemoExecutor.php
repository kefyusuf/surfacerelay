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
        if ($definition->id !== 'orders.hold_current') {
            throw new RuntimeException('Unsupported order demo action.');
        }

        $order = $context->require(ContextRequirement::CurrentRecord)->value;
        if (!$order instanceof Order) {
            throw new RuntimeException('Order demo current record is invalid.');
        }

        $order->held = true;
        $order->save();
        $this->holdExecutions++;

        return [
            'orderId' => $order->getKey(),
            'held' => true,
        ];
    }
}
