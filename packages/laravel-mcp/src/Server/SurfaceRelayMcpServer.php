<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Server;

use Laravel\Mcp\Server as LaravelMcpServer;
use Laravel\Mcp\Server\Contracts\Transport;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposureRegistry;
use SurfaceRelay\LaravelMcp\Projection\McpToolProjector;

/**
 * Dynamic MCP server over the bridge's explicit exposure registry.
 *
 * The host application still owns transport, route, authentication, OAuth,
 * and network policy. Starting this server only materializes already-explicit
 * SurfaceRelay MCP exposures into Laravel MCP Tool instances.
 */
final class SurfaceRelayMcpServer extends LaravelMcpServer
{
    protected string $name = 'SurfaceRelay';

    protected string $version = '0.1.0';

    protected string $instructions =
        'SurfaceRelay exposes explicitly configured application actions. Tool discovery does not imply invocation authorization.';

    public function __construct(
        Transport $transport,
        private readonly McpActionExposureRegistry $exposures,
        private readonly McpToolProjector $projector,
    ) {
        parent::__construct($transport);
    }

    protected function boot(): void
    {
        $this->tools = $this->projector->projectAll(
            $this->exposures->all(),
        );
    }
}
