<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Protocol-neutral invocation request. Action identity is always explicit
 * (id + version); there is no latest-version behavior or fallback. This
 * object is constructed by trusted runtime wiring, never hydrated from
 * HTTP/WebMCP/MCP payloads, and carries no binding resolution.
 */
final readonly class ActionCall
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public string $actionId,
        public int $actionVersion,
        public array $input,
        public InvocationContext $context,
    ) {
        if ($this->actionId === '') {
            throw new \InvalidArgumentException('ActionCall actionId must be a non-empty string.');
        }
        if ($this->actionVersion < 1) {
            throw new \InvalidArgumentException('ActionCall actionVersion must be >= 1.');
        }
    }
}
