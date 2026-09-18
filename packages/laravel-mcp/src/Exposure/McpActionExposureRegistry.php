<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Exposure;

use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Enums\ActionScope;

/**
 * Explicit MCP allow-list keyed by exact Action Definition identity.
 *
 * The underlying ActionRegistry is storage only. Exposure always resolves a
 * caller-selected exact id + version via get(); registry-wide enumeration is
 * never used as exposure authority.
 */
final class McpActionExposureRegistry
{
    /** @var array<string, McpActionExposure> */
    private array $exposures = [];

    public function __construct(
        private readonly ActionRegistry $actions,
    ) {}

    public function expose(string $id, int $version): void
    {
        $definition = $this->actions->get($id, $version);

        if (!in_array(
            $definition->scope,
            [ActionScope::Portable, ActionScope::Headless],
            true,
        )) {
            throw InvalidMcpActionExposure::unsupportedScope($definition);
        }

        $key = $definition->id.'@'.$definition->version;

        if (isset($this->exposures[$key])) {
            throw InvalidMcpActionExposure::duplicate($definition);
        }

        $this->exposures[$key] = new McpActionExposure($definition);
    }

    /** @return list<McpActionExposure> */
    public function all(): array
    {
        $exposures = array_values($this->exposures);

        usort(
            $exposures,
            static fn (McpActionExposure $a, McpActionExposure $b): int
                => [$a->definition->id, $a->definition->version]
                    <=> [$b->definition->id, $b->definition->version],
        );

        return $exposures;
    }
}
