<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;

final readonly class PrepListActionGateway
{
    public function __construct(
        private ActionBus $bus,
        private PrepListInvocationContextFactory $contextFactory,
    ) {}

    /** @return array{itemId: string, name: string} */
    public function addItem(string $name): array
    {
        $outcome = $this->bus->dispatch(new ActionCall(
            actionId: 'prep_list.add_item',
            actionVersion: 1,
            input: ['name' => $name],
            context: $this->contextFactory->create(),
        ));

        if (!$outcome->completed) {
            throw new \RuntimeException(sprintf(
                'Prep List action did not complete: %s.',
                $outcome->halt?->code ?? 'unknown_halt',
            ));
        }

        /** @var array{itemId: string, name: string} */
        return $outcome->state->output;
    }
}
