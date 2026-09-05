<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Registry;

use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Plain PHP in-memory registry. No Laravel container, database, cache,
 * filesystem, HTTP, or adapter dependencies.
 *
 * Keying is internal convenience only (`{id}@{version}`); the public Action
 * Identity remains the separate canonical `id` + `version` fields.
 */
final class InMemoryActionRegistry implements ActionRegistry
{
    /** @var array<string, ActionDefinition> */
    private array $definitions = [];

    public function register(ActionDefinition $definition): void
    {
        $key = $this->key($definition->id, $definition->version);
        if (isset($this->definitions[$key])) {
            throw new DuplicateActionDefinition($definition->id, $definition->version);
        }
        $this->definitions[$key] = $definition;
    }

    public function has(string $id, int $version): bool
    {
        return isset($this->definitions[$this->key($id, $version)]);
    }

    public function get(string $id, int $version): ActionDefinition
    {
        $key = $this->key($id, $version);
        if (!isset($this->definitions[$key])) {
            throw new ActionDefinitionNotFound($id, $version);
        }
        return $this->definitions[$key];
    }

    public function all(): array
    {
        $definitions = array_values($this->definitions);
        usort(
            $definitions,
            static fn (ActionDefinition $a, ActionDefinition $b): int
                => [$a->id, $a->version] <=> [$b->id, $b->version],
        );
        return $definitions;
    }

    private function key(string $id, int $version): string
    {
        return $id . '@' . $version;
    }
}
