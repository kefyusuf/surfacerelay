<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use SurfaceRelay\Laravel\Contracts\ActionAuthorizer;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class PrepListAuthorizer implements ActionAuthorizer
{
    public int $calls = 0;
    /** @var array<string, mixed>|null */
    public ?array $lastInput = null;
    public ?InvocationContext $lastContext = null;

    public function allows(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): bool {
        ++$this->calls;
        $this->lastInput = $input;
        $this->lastContext = $context;

        return $definition->id === 'prep_list.add_item'
            && $definition->version === 1;
    }
}
