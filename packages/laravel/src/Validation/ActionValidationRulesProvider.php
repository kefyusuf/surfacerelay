<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Validation;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Supplies explicit Laravel validation rules for one exact ActionDefinition
 * identity (id + version). Rules are deliberate Laravel-runtime enforcement
 * metadata — they never enter the protocol-neutral ActionDefinition contract,
 * are never derived from its JSON Schema, and are never auto-generated here.
 *
 * The provider resolves rules only; it performs no discovery, registry
 * fallback, version negotiation, or authorization.
 */
interface ActionValidationRulesProvider
{
    /**
     * Laravel Validator rule shapes are preserved as configured
     * (pipe strings, arrays, Rule objects, closures).
     *
     * @return array<string, mixed>
     */
    public function rulesFor(ActionDefinition $definition): array;
}
