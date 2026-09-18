<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\LaravelMcp\Projection\InvalidMcpToolProjection;
use SurfaceRelay\LaravelMcp\Projection\McpToolNameProjector;

final class McpToolNameProjectorTest extends TestCase
{
    public function test_projects_exact_action_identity_to_deterministic_tool_name(): void
    {
        $projector = new McpToolNameProjector();
        $definition = $this->definition('orders.cancel', 2);

        self::assertSame('orders.cancel.v2', $projector->project($definition));
        self::assertSame('orders.cancel.v2', $projector->project($definition));
    }

    public function test_different_action_versions_project_to_different_tool_names(): void
    {
        $projector = new McpToolNameProjector();

        self::assertSame('orders.cancel.v1', $projector->project($this->definition('orders.cancel', 1)));
        self::assertSame('orders.cancel.v2', $projector->project($this->definition('orders.cancel', 2)));
    }

    public function test_projected_name_longer_than_128_characters_fails_closed_without_truncation(): void
    {
        $projector = new McpToolNameProjector();
        $id = str_repeat('a', 63).'.'.str_repeat('b', 63);

        $this->expectException(InvalidMcpToolProjection::class);
        $this->expectExceptionMessage('128');

        $projector->project($this->definition($id, 1));
    }

    public function test_canonical_action_id_charset_projects_without_rewriting(): void
    {
        $projector = new McpToolNameProjector();

        self::assertSame(
            'orders_v2.find_item.v17',
            $projector->project($this->definition('orders_v2.find_item', 17)),
        );
    }

    private function definition(string $id, int $version): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Projected action',
            description: 'Exercises deterministic MCP tool naming.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
