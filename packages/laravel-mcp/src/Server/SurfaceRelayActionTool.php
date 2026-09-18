<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Server;

use Laravel\Mcp\Server\Tool;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;

/**
 * Discovery-only MCP representation of one explicitly exposed Action.
 *
 * Task 3 deliberately owns no invocation behavior. Task 4 adds the gateway
 * and handle() path; this class currently projects canonical discovery data
 * only and creates no authorization or trusted runtime authority.
 */
final class SurfaceRelayActionTool extends Tool
{
    public function __construct(
        private readonly ActionDefinition $definition,
        private readonly string $projectedName,
    ) {}

    public function name(): string
    {
        return $this->projectedName;
    }

    public function title(): string
    {
        return $this->definition->title;
    }

    public function description(): string
    {
        return $this->definition->description;
    }

    /**
     * @return array<string, mixed>
     */
    public function annotations(): array
    {
        return $this->definition->effect === ActionEffect::Read
            ? ['readOnlyHint' => true]
            : [];
    }

    /**
     * Copy the canonical Action input schema without rebuilding or widening
     * it through Laravel MCP's JsonSchema builder.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $annotations = $this->annotations();

        return [
            'name' => $this->name(),
            'title' => $this->title(),
            'description' => $this->description(),
            'inputSchema' => $this->definition->inputSchema,
            'annotations' => $annotations === [] ? (object) [] : $annotations,
        ];
    }
}
