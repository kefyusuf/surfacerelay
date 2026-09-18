<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Exposure;

use SurfaceRelay\Laravel\Definition\ActionDefinition;

/**
 * Invalid MCP exposure configuration.
 *
 * Exposure failures are application/developer configuration errors. They do
 * not become ActionResult errors and they grant no invocation authority.
 */
final class InvalidMcpActionExposure extends \LogicException
{
    public static function unsupportedScope(ActionDefinition $definition): self
    {
        return new self(sprintf(
            'Action Definition "%s" version %d uses unsupported MCP exposure scope "%s".',
            $definition->id,
            $definition->version,
            $definition->scope->value,
        ));
    }

    public static function duplicate(ActionDefinition $definition): self
    {
        return new self(sprintf(
            'Action Definition "%s" version %d is already exposed to MCP.',
            $definition->id,
            $definition->version,
        ));
    }
}
