<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Support;

use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
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
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\LaravelMcp\Invocation\McpActionGateway;

final class McpTestRuntime
{
    /**
     * @param list<ContextRequirement> $requirements
     */
    public static function definition(
        string $id = 'orders.find',
        int $version = 1,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Test MCP action',
            description: 'Exercises the MCP invocation bridge.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    public static function composer(
        mixed $actor = 'trusted-user',
        mixed $tenant = 'trusted-tenant',
    ): TrustedContextComposer {
        return new TrustedContextComposer(
            new StaticResolvedValueActorResolver($actor),
            new StaticResolvedValueTenantResolver($tenant),
        );
    }

    public static function bus(
        ActionDefinition $definition,
        CapturingActionPipelineAuditor $auditor,
        mixed $executionOutput = ['executed' => true],
    ): ActionBus {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = null;
            if ($stage === ActionPipelineStage::Execution) {
                $behavior = static fn (ActionPipelineState $state): ActionPipelineState
                    => $state->withOutput($executionOutput);
            }
            $handlers[] = new McpTestStageHandler($stage, $behavior);
        }

        return new ActionBus($registry, $auditor, $handlers);
    }

    /**
     * @return array{gateway: McpActionGateway, auditor: CapturingActionPipelineAuditor}
     */
    public static function gateway(
        ActionDefinition $definition,
        mixed $actor = 'trusted-user',
        mixed $tenant = 'trusted-tenant',
        mixed $executionOutput = ['executed' => true],
    ): array {
        $auditor = new CapturingActionPipelineAuditor();

        return [
            'gateway' => new McpActionGateway(
                self::bus($definition, $auditor, $executionOutput),
                new ActionResultNormalizer(),
                self::composer($actor, $tenant),
            ),
            'auditor' => $auditor,
        ];
    }
}

final class StaticResolvedValueActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return $this->value === null
            ? null
            : new ResolvedTrustedValue(
                $this->value,
                new ContextProvenance('test.mcp.actor'),
            );
    }
}

final class StaticResolvedValueTenantResolver implements TenantResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return $this->value === null
            ? null
            : new ResolvedTrustedValue(
                $this->value,
                new ContextProvenance('test.mcp.tenant'),
            );
    }
}

final class McpTestStageHandler implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly ActionPipelineStage $pipelineStage,
        private readonly ?\Closure $behavior = null,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if ($this->behavior === null) {
            return ActionPipelineDecision::continueWith($state);
        }

        $next = ($this->behavior)($state);

        return $next instanceof ActionPipelineDecision
            ? $next
            : ActionPipelineDecision::continueWith($next);
    }
}

final class CapturingActionPipelineAuditor implements ActionPipelineAuditor
{
    /** @var list<ActionCall> */
    public array $calls = [];

    /** @var list<ActionPipelineOutcome> */
    public array $outcomes = [];

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls[] = $call;
        $this->outcomes[] = $outcome;
    }
}
