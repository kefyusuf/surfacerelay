<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Supplies the explicit Laravel authorization rule for one exact
 * ActionDefinition identity (id + version). No discovery, registry fallback,
 * version negotiation, or automatic ability naming.
 */
interface ActionAuthorizationRulesProvider
{
    public function ruleFor(ActionDefinition $definition): LaravelAuthorizationRule;
}
