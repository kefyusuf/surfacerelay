<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Unit;

use Laravel\Mcp\Request;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposure;
use SurfaceRelay\LaravelMcp\Projection\InvalidMcpToolProjection;
use SurfaceRelay\LaravelMcp\Projection\McpToolNameProjector;
use SurfaceRelay\LaravelMcp\Projection\McpToolProjector;
use SurfaceRelay\LaravelMcp\Server\SurfaceRelayActionTool;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestRuntime;

final class SurfaceRelayActionToolTest extends TestCase
{
    public function test_read_action_discovery_shape_preserves_canonical_schema_and_only_read_only_hint(): void
    {
        $schema = [
            'type' => 'object',
            'properties' => [
                'orderId' => ['type' => 'string', 'minLength' => 1],
            ],
            'required' => ['orderId'],
            'additionalProperties' => false,
        ];
        $definition = $this->definition(
            id: 'orders.find',
            version: 1,
            effect: ActionEffect::Read,
            inputSchema: $schema,
            outputSchema: ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
        );

        $tool = new SurfaceRelayActionTool($definition, 'orders.find.v1', McpTestRuntime::gateway($definition)['gateway']);

        self::assertSame(
            [
                'name' => 'orders.find.v1',
                'title' => 'Find order',
                'description' => 'Finds an order visible to the current trusted tenant.',
                'inputSchema' => $schema,
                'annotations' => ['readOnlyHint' => true],
            ],
            $tool->toArray(),
        );
        self::assertArrayNotHasKey('outputSchema', $tool->toArray());
    }

    public function test_write_action_emits_no_inferred_cross_protocol_hints(): void
    {
        $definition = $this->definition(
            id: 'orders.cancel',
            version: 2,
            effect: ActionEffect::DestructiveWrite,
            inputSchema: ['type' => 'object'],
        );

        $tool = new SurfaceRelayActionTool($definition, 'orders.cancel.v2', McpTestRuntime::gateway($definition)['gateway']);
        $array = $tool->toArray();

        self::assertEquals((object) [], $array['annotations']);
        self::assertArrayNotHasKey('destructiveHint', (array) $array['annotations']);
        self::assertArrayNotHasKey('idempotentHint', (array) $array['annotations']);
        self::assertArrayNotHasKey('openWorldHint', (array) $array['annotations']);
        self::assertArrayNotHasKey('outputSchema', $array);
    }

    public function test_projector_preserves_exposure_order_and_exact_identity(): void
    {
        $first = $this->definition('alpha.action', 1, ActionEffect::Read, ['type' => 'object']);
        $second = $this->definition('zeta.action', 2, ActionEffect::ReversibleWrite, ['type' => 'object']);

        $projector = new McpToolProjector(new McpToolNameProjector(), McpTestRuntime::gateway($first)['gateway']);

        $tools = $projector->projectAll([
            new McpActionExposure($first),
            new McpActionExposure($second),
        ]);

        self::assertSame(
            ['alpha.action.v1', 'zeta.action.v2'],
            array_map(static fn (SurfaceRelayActionTool $tool): string => $tool->name(), $tools),
        );
    }

    public function test_projector_rejects_non_exposure_values(): void
    {
        $projector = new McpToolProjector(new McpToolNameProjector(), McpTestRuntime::gateway($this->definition('tools.probe', 1, ActionEffect::Read, ['type' => 'object']))['gateway']);

        $this->expectException(InvalidMcpToolProjection::class);
        $this->expectExceptionMessage('McpActionExposure');

        $projector->projectAll([new \stdClass()]);
    }

    public function test_projector_rejects_duplicate_projected_tool_names(): void
    {
        $definition = $this->definition('orders.find', 1, ActionEffect::Read, ['type' => 'object']);
        $exposure = new McpActionExposure($definition);
        $projector = new McpToolProjector(new McpToolNameProjector(), McpTestRuntime::gateway($definition)['gateway']);

        $this->expectException(InvalidMcpToolProjection::class);
        $this->expectExceptionMessage('duplicate');

        $projector->projectAll([$exposure, $exposure]);
    }

    public function test_handle_routes_arguments_and_meta_through_gateway_and_returns_structured_action_result(): void
    {
        $definition = $this->definition(
            id: 'orders.find',
            version: 1,
            effect: ActionEffect::Read,
            inputSchema: ['type' => 'object'],
        );
        ['gateway' => $gateway, 'auditor' => $auditor] = McpTestRuntime::gateway(
            $definition,
            executionOutput: ['orderId' => '42'],
        );

        $tool = new SurfaceRelayActionTool($definition, 'orders.find.v1', $gateway);

        $response = $tool->handle(new Request(
            arguments: ['orderId' => '42'],
            meta: [
                'io.surfacerelay/idempotencyKey' => 'idem-42',
                'other/vendor' => 'ignored',
            ],
        ));

        self::assertSame(
            [
                'status' => 'succeeded',
                'correlationId' => $auditor->calls[0]->context->correlationId,
                'data' => ['orderId' => '42'],
            ],
            $response->getStructuredContent(),
        );
        self::assertFalse($response->responses()->first()->isError());
    }

    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed>|null $outputSchema
     */
    private function definition(
        string $id,
        int $version,
        ActionEffect $effect,
        array $inputSchema,
        ?array $outputSchema = null,
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: $id === 'orders.find' ? 'Find order' : 'Projected action',
            description: $id === 'orders.find'
                ? 'Finds an order visible to the current trusted tenant.'
                : 'Exercises MCP discovery projection.',
            inputSchema: $inputSchema,
            scope: ActionScope::Portable,
            effect: $effect,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: $outputSchema,
        );
    }
}
