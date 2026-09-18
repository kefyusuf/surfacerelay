<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Projection;

use SurfaceRelay\LaravelMcp\Exposure\McpActionExposure;
use SurfaceRelay\LaravelMcp\Invocation\McpActionGateway;
use SurfaceRelay\LaravelMcp\Server\SurfaceRelayActionTool;

/**
 * Converts an already-explicit exposure list into MCP discovery tools.
 *
 * This class does not discover Actions, authorize calls, or synthesize
 * RuntimeBindings. The caller controls exposure membership and order.
 */
final readonly class McpToolProjector
{
    public function __construct(
        private McpToolNameProjector $names,
        private McpActionGateway $gateway,
    ) {}

    /**
     * @param array<array-key, mixed> $exposures
     * @return list<SurfaceRelayActionTool>
     */
    public function projectAll(array $exposures): array
    {
        $tools = [];
        $seen = [];

        foreach ($exposures as $exposure) {
            if (!$exposure instanceof McpActionExposure) {
                throw InvalidMcpToolProjection::invalidExposure($exposure);
            }

            $name = $this->names->project($exposure->definition);

            if (isset($seen[$name])) {
                throw InvalidMcpToolProjection::duplicateName($name);
            }

            $seen[$name] = true;
            $tools[] = new SurfaceRelayActionTool(
                $exposure->definition,
                $name,
                $this->gateway,
            );
        }

        return $tools;
    }
}
