<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final readonly class PrepListActionExecutor implements ActionExecutor
{
    public function __construct(
        private AddPrepListItem $addItem,
    ) {}

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        if ($definition->id !== 'prep_list.add_item' || $definition->version !== 1) {
            throw new \RuntimeException(sprintf(
                'Prep List executor does not support Action Definition "%s" version %d.',
                $definition->id,
                $definition->version,
            ));
        }

        $name = $input['name'] ?? null;
        if (!is_string($name)) {
            throw new \LogicException('Validated Prep List input must contain string "name".');
        }

        return $this->addItem->handle($name);
    }
}
