<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Integration;

use Laravel\Mcp\Server\Transport\FakeTransporter;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\LaravelMcp\Exposure\McpActionExposureRegistry;
use SurfaceRelay\LaravelMcp\Server\SurfaceRelayActionTool;
use SurfaceRelay\LaravelMcp\Server\SurfaceRelayMcpServer;
use SurfaceRelay\LaravelMcp\Tests\Support\CapturingActionPipelineAuditor;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestRuntime;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestStageHandler;
use SurfaceRelay\LaravelMcp\Tests\TestCase;

final class SurfaceRelayMcpServerIntegrationTest extends TestCase
{
    public function test_provider_registers_one_explicit_exposure_registry_singleton(): void
    {
        $registry = new InMemoryActionRegistry();
        $this->app->instance(ActionRegistry::class, $registry);

        $first = $this->app->make(McpActionExposureRegistry::class);
        $second = $this->app->make(McpActionExposureRegistry::class);

        self::assertSame($first, $second);
        self::assertSame([], $first->all());
    }

    public function test_tools_list_contains_only_explicitly_exposed_actions_in_deterministic_order(): void
    {
        $alpha = $this->definition('alpha.find', 1);
        $hidden = $this->definition('hidden.find', 1);
        $zeta = $this->definition('zeta.find', 2);

        $this->installRuntime([$alpha, $hidden, $zeta]);

        $exposures = $this->app->make(McpActionExposureRegistry::class);
        $exposures->expose('zeta.find', 2);
        $exposures->expose('alpha.find', 1);

        $server = $this->app->make(SurfaceRelayMcpServer::class, [
            'transport' => new FakeTransporter(),
        ]);
        $server->start();

        self::assertSame(
            ['alpha.find.v1', 'zeta.find.v2'],
            $server->createContext()->tools()->map(
                static fn (SurfaceRelayActionTool $tool): string => $tool->name(),
            )->all(),
        );
    }

    public function test_tools_call_executes_explicit_action_through_existing_gateway_and_bus(): void
    {
        $definition = $this->definition('orders.find', 1);
        $auditor = $this->installRuntime([$definition]);

        $exposures = $this->app->make(McpActionExposureRegistry::class);
        $exposures->expose('orders.find', 1);

        $tool = $this->projectedTool('orders.find.v1');

        $response = SurfaceRelayMcpServer::tool($tool, ['orderId' => '42']);

        self::assertCount(1, $auditor->calls);
        $call = $auditor->calls[0];

        $response
            ->assertOk()
            ->assertStructuredContent([
                'status' => 'succeeded',
                'correlationId' => $call->context->correlationId,
                'data' => [
                    'action' => 'orders.find',
                    'input' => ['orderId' => '42'],
                ],
            ]);

        self::assertSame('mcp', $call->context->surface);
        self::assertSame('orders.find', $call->actionId);
        self::assertSame(1, $call->actionVersion);
    }

    public function test_rejected_action_remains_structured_action_result_not_mcp_transport_error(): void
    {
        $definition = $this->definition(
            'orders.current',
            1,
            requirements: [ContextRequirement::CurrentRecord],
        );
        $auditor = $this->installRuntime([$definition]);

        $this->app->make(McpActionExposureRegistry::class)->expose('orders.current', 1);
        $tool = $this->projectedTool('orders.current.v1');

        $response = SurfaceRelayMcpServer::tool($tool, ['current_record' => ['id' => 999]]);

        self::assertCount(1, $auditor->calls);
        $correlationId = $auditor->calls[0]->context->correlationId;

        $response
            ->assertOk()
            ->assertStructuredContent([
                'status' => 'rejected',
                'correlationId' => $correlationId,
                'error' => [
                    'code' => 'required_context_missing',
                    'message' => 'Required trusted context is unavailable.',
                    'details' => ['requirements' => ['current_record']],
                ],
            ]);
    }

    public function test_confirmation_required_remains_structured_action_result_not_mcp_transport_error(): void
    {
        $definition = $this->definition(
            'orders.cancel',
            2,
            effect: ActionEffect::DestructiveWrite,
            requirements: [ContextRequirement::HumanConfirmation],
        );
        $auditor = $this->installRuntime([$definition], confirmationActionId: 'orders.cancel');

        $this->app->make(McpActionExposureRegistry::class)->expose('orders.cancel', 2);
        $tool = $this->projectedTool('orders.cancel.v2');

        $response = SurfaceRelayMcpServer::tool($tool, ['orderId' => '42']);

        self::assertCount(1, $auditor->calls);
        $correlationId = $auditor->calls[0]->context->correlationId;

        $response
            ->assertOk()
            ->assertStructuredContent([
                'status' => 'confirmation_required',
                'correlationId' => $correlationId,
                'confirmation' => [
                    'challengeId' => 'challenge-task5',
                    'summary' => 'Confirm Task 5 integration action.',
                    'expiresAt' => null,
                ],
            ]);
    }

    /**
     * @param list<ActionDefinition> $definitions
     */
    private function installRuntime(
        array $definitions,
        ?string $confirmationActionId = null,
    ): CapturingActionPipelineAuditor {
        $registry = new InMemoryActionRegistry();
        foreach ($definitions as $definition) {
            $registry->register($definition);
        }

        // Load the Task 4 support file through its PSR-4 primary class before
        // using the additional test-only classes declared in that same file.
        $trustedContext = McpTestRuntime::composer('trusted-user', 'trusted-tenant');

        $auditor = new CapturingActionPipelineAuditor();
        $handlers = [];

        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = null;

            if ($stage === ActionPipelineStage::Confirmation && $confirmationActionId !== null) {
                $behavior = static function (ActionPipelineState $state) use ($confirmationActionId): ActionPipelineDecision|ActionPipelineState {
                    if ($state->definition->id !== $confirmationActionId) {
                        return $state;
                    }

                    return ActionPipelineDecision::halt(
                        new ActionPipelineHalt(
                            CoreActionErrorCode::CONFIRMATION_REQUIRED,
                            confirmation: new ConfirmationChallenge(
                                'challenge-task5',
                                'Confirm Task 5 integration action.',
                            ),
                        ),
                        $state,
                    );
                };
            }

            if ($stage === ActionPipelineStage::Execution) {
                $behavior = static fn (ActionPipelineState $state): ActionPipelineState
                    => $state->withOutput([
                        'action' => $state->definition->id,
                        'input' => $state->input,
                    ]);
            }

            $handlers[] = new McpTestStageHandler($stage, $behavior);
        }

        $bus = new ActionBus($registry, $auditor, $handlers);

        $this->app->instance(ActionRegistry::class, $registry);
        $this->app->instance(ActionBus::class, $bus);
        $this->app->instance(TrustedContextComposer::class, $trustedContext);
        $this->app->instance(ActionResultNormalizer::class, new ActionResultNormalizer());

        return $auditor;
    }

    private function projectedTool(string $name): SurfaceRelayActionTool
    {
        $server = $this->app->make(SurfaceRelayMcpServer::class, [
            'transport' => new FakeTransporter(),
        ]);
        $server->start();

        /** @var SurfaceRelayActionTool|null $tool */
        $tool = $server->createContext()->tools()->first(
            static fn (SurfaceRelayActionTool $tool): bool => $tool->name() === $name,
        );

        self::assertNotNull($tool, 'Expected projected MCP tool to be present.');

        return $tool;
    }

    /**
     * @param list<ContextRequirement> $requirements
     */
    private function definition(
        string $id,
        int $version,
        ActionEffect $effect = ActionEffect::Read,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Task 5 '.$id,
            description: 'Exercises dynamic Laravel MCP server wiring.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: $effect,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }
}
