<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Contracts;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * In-memory-safe registry of explicitly registered Action Definitions keyed
 * by exact Action Identity (id + version). This is a storage contract only:
 * it is not discovery, authorization, invocation, adapter exposure, or
 * version negotiation. Missing versions never fall back to other versions.
 */
interface ActionRegistry
{
    public function register(ActionDefinition $definition): void;

    public function has(string $id, int $version): bool;

    public function get(string $id, int $version): ActionDefinition;

    /** @return list<ActionDefinition> Deterministically ordered: id ASC, then version ASC. */
    public function all(): array;
}
