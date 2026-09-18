<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Projection;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Deterministic exact Action identity -> MCP tool name projection.
 */
final class McpToolNameProjector
{
    private const string NAME_PATTERN = '/^[A-Za-z0-9_.-]{1,128}$/D';

    public function project(ActionDefinition $definition): string
    {
        $name = $definition->id.'.v'.$definition->version;

        if (preg_match(self::NAME_PATTERN, $name) !== 1) {
            throw InvalidMcpToolProjection::invalidName($name);
        }

        return $name;
    }
}
