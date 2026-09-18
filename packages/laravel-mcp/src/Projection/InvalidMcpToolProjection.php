<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Projection;

final class InvalidMcpToolProjection extends \LogicException
{
    public static function invalidName(string $name): self
    {
        return new self(sprintf(
            'Projected MCP tool name "%s" must match ^[A-Za-z0-9_.-]{1,128}$ and must not be rewritten or truncated.',
            $name,
        ));
    }

    public static function invalidExposure(mixed $value): self
    {
        return new self(sprintf(
            'MCP tool projection expects only %s values; received %s.',
            \SurfaceRelay\LaravelMcp\Exposure\McpActionExposure::class,
            get_debug_type($value),
        ));
    }

    public static function duplicateName(string $name): self
    {
        return new self(sprintf(
            'MCP tool projection produced duplicate tool name "%s".',
            $name,
        ));
    }
}
