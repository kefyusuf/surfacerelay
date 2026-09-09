<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ContextRequirement;

/** Shared kernel predicate for whether one action requires human confirmation. */
final class ConfirmationRequirement
{
    public static function isRequired(ActionDefinition $definition): bool
    {
        return $definition->risk === ActionRisk::Consequential
            || in_array(ContextRequirement::HumanConfirmation, $definition->contextRequirements, true);
    }

    private function __construct()
    {
    }
}
