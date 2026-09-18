<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Invocation;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Result\ActionResult;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;

/**
 * Thin MCP invocation adapter over the existing SurfaceRelay ActionBus.
 *
 * The gateway creates diagnostic invocation context, resolves trusted actor /
 * tenant state only through injected trusted resolvers, carries bounded
 * confirmation/idempotency candidates, and delegates all business semantics
 * to the existing pipeline.
 */
final readonly class McpActionGateway
{
    public function __construct(
        private ActionBus $bus,
        private ActionResultNormalizer $normalizer,
        private TrustedContextComposer $trustedContext,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function invoke(
        ActionDefinition $definition,
        array $input,
        McpInvocationMetadata $metadata,
    ): ActionResult {
        $context = new InvocationContext(
            surface: 'mcp',
            correlationId: bin2hex(random_bytes(16)),
            trustedContext: $this->trustedContext->resolve(),
            idempotencyKey: $metadata->idempotencyKey,
        );

        $outcome = $this->bus->dispatch(new ActionCall(
            actionId: $definition->id,
            actionVersion: $definition->version,
            input: $input,
            context: $context,
            bindingId: null,
            confirmationReceipt: $metadata->confirmationReceipt,
        ));

        return $this->normalizer->normalize($outcome);
    }
}
