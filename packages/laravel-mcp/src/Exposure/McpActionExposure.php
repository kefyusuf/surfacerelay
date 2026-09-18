<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Exposure;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * One explicit MCP exposure candidate.
 *
 * This value carries only the exact Action Definition. It is not discovery
 * authorization, invocation authorization, trusted context, or a binding.
 */
final readonly class McpActionExposure
{
    public function __construct(
        public ActionDefinition $definition,
    ) {}
}
