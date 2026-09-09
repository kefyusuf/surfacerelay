<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationConfigurationViolation;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class IdempotencyReplayOrchestrationTest extends TestCase
{
    private const string KEY = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const string INTENT = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_completed_replay_skips_confirmation_and_execution_but_reruns_output_policy_and_audits_once(): void
    {
        $definition = $this->definition(ActionRisk::Consequential);
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $log = [];
        $auditor = new IdempotencyReplayAuditor($log);
        $handlers = $this->handlers($log);
        $handlers[ActionPipelineStage::Idempotency->value] = new IdempotencyReplayStage(
            ActionPipelineStage::Idempotency,
            $log,
            static fn (ActionPipelineState $state) => $state
                ->withIdempotencyPlan(IdempotencyExecutionPlan::replay(
                    self::KEY,
                    self::INTENT,
                    ['stored' => 'raw'],
                ))
                ->withOutput(['stored' => 'raw']),
        );
        $handlers[ActionPipelineStage::OutputPolicy->value] = new IdempotencyReplayStage(
            ActionPipelineStage::OutputPolicy,
            $log,
            static fn (ActionPipelineState $state) => $state->withOutput([
                'projected' => $state->output,
            ]),
        );

        $outcome = (new ActionBus($registry, $auditor, $handlers))->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['amount' => 100],
            new InvocationContext('webmcp', 'corr-retry'),
            bindingId: 'binding-new',
        ));

        self::assertSame(
            ['input_validation', 'authorization', 'idempotency', 'output_policy', 'audit'],
            $log,
            'Exact completed replay must not consume confirmation or call application execution again.',
        );
        self::assertTrue($outcome->completed);
        self::assertSame(['projected' => ['stored' => 'raw']], $outcome->state->output);
        self::assertSame('corr-retry', $outcome->state->context->correlationId);
        self::assertFalse($outcome->state->context->has(ContextRequirement::HumanConfirmation));
        self::assertSame(1, $auditor->calls);
    }

    public function test_completed_consequential_replay_rejects_pre_materialized_confirmation_authority(): void
    {
        $definition = $this->definition(ActionRisk::Consequential);
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $log = [];
        $handlers = $this->handlers($log);
        $handlers[ActionPipelineStage::Idempotency->value] = new IdempotencyReplayStage(
            ActionPipelineStage::Idempotency,
            $log,
            static fn (ActionPipelineState $state) => $state
                ->withIdempotencyPlan(IdempotencyExecutionPlan::replay(
                    self::KEY,
                    self::INTENT,
                    ['stored' => 'raw'],
                ))
                ->withOutput(['stored' => 'raw']),
        );
        $context = new InvocationContext('webmcp', 'corr-preloaded', [
            new TrustedContextEntry(
                ContextRequirement::HumanConfirmation,
                'preloaded-authority',
                new ContextProvenance('bad.provider'),
            ),
        ]);

        $this->expectException(ConfirmationConfigurationViolation::class);
        (new ActionBus($registry, new IdempotencyReplayAuditor($log), $handlers))->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['amount' => 100],
            $context,
        ));
    }

    /** @return array<string, IdempotencyReplayStage> */
    private function handlers(array &$log): array
    {
        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = $stage === ActionPipelineStage::Execution
                ? static fn (ActionPipelineState $state) => $state->withOutput(['unsafe' => 'executed'])
                : null;
            $handlers[$stage->value] = new IdempotencyReplayStage($stage, $log, $behavior);
        }
        return $handlers;
    }

    private function definition(ActionRisk $risk): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Refund order',
            description: 'Refunds an exact order intent.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: $risk,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}

final class IdempotencyReplayStage implements ActionPipelineStageHandler
{
    /** @var list<string> */
    private array $log;

    public function __construct(
        private readonly ActionPipelineStage $pipelineStage,
        array &$log,
        private readonly ?\Closure $behavior = null,
    ) {
        $this->log = &$log;
    }

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->log[] = $this->pipelineStage->value;
        if ($this->behavior !== null) {
            return ActionPipelineDecision::continueWith(($this->behavior)($state));
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class IdempotencyReplayAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;
    /** @var list<string> */
    private array $log;

    public function __construct(array &$log)
    {
        $this->log = &$log;
    }

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
        $this->log[] = 'audit';
    }
}
