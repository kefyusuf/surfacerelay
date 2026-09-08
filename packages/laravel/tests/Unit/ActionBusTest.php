<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Registry\ActionDefinitionNotFound;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\InvalidPipelineConfiguration;
use SurfaceRelay\Laravel\Runtime\Pipeline\PipelineInvariantViolation;

final class ActionBusTest extends TestCase
{
    private SpyAuditor $auditor;

    protected function setUp(): void
    {
        $this->auditor = new SpyAuditor();
    }

    // ------------------------------------------------------------------
    // Canonical order / successful flow
    // ------------------------------------------------------------------

    public function test_shuffled_handler_injection_cannot_reorder_canonical_pipeline(): void
    {
        $log = [];
        $this->auditor = new SpyAuditor($log);
        $bus = $this->makeBus($this->shuffledHandlers($log));

        $outcome = $bus->dispatch($this->makeCall());

        self::assertSame(
            ['input_validation', 'authorization', 'idempotency', 'confirmation', 'execution', 'output_policy', 'audit'],
            $log,
            'Canonical stage order is imposed by the kernel; audit observes the final outcome last.',
        );
        self::assertTrue($outcome->completed);
    }

    public function test_successful_pipeline_completes_with_execution_output(): void
    {
        $bus = $this->makeBus($this->defaultHandlers());

        $outcome = $bus->dispatch($this->makeCall());

        self::assertTrue($outcome->completed);
        self::assertNull($outcome->haltedAt);
        self::assertNull($outcome->halt);
        self::assertTrue($outcome->state->hasOutput);
        self::assertSame('executed', $outcome->state->output);
        self::assertSame(1, $this->auditor->calls);
    }

    public function test_state_transformation_flows_through_later_stages(): void
    {
        $seenByAuthorization = null;
        $log = [];

        $validation = new FakeStageHandler(ActionPipelineStage::InputValidation, $log,
            static fn (ActionPipelineState $state) => $state->withInput(['query' => 'normalized']));
        $authorization = new FakeStageHandler(ActionPipelineStage::Authorization, $log,
            function (ActionPipelineState $state) use (&$seenByAuthorization) {
                $seenByAuthorization = $state->input;

                return $state;
            });
        $execution = new FakeStageHandler(ActionPipelineStage::Execution, $log,
            static fn (ActionPipelineState $state) => $state->withOutput('raw-result'));
        $outputPolicy = new FakeStageHandler(ActionPipelineStage::OutputPolicy, $log,
            static fn (ActionPipelineState $state) => $state->withOutput('transformed:' . $state->output));
        $confirmation = new FakeStageHandler(ActionPipelineStage::Confirmation, $log);
        $idempotency = new FakeStageHandler(ActionPipelineStage::Idempotency, $log);

        $bus = $this->makeBus([$validation, $authorization, $idempotency, $confirmation, $execution, $outputPolicy]);
        $outcome = $bus->dispatch($this->makeCall());

        self::assertSame(['query' => 'normalized'], $seenByAuthorization,
            'Authorization observes the transformed input, not the raw caller input.');
        self::assertTrue($outcome->completed);
        self::assertSame('transformed:raw-result', $outcome->state->output);
    }

    // ------------------------------------------------------------------
    // Short-circuit semantics
    // ------------------------------------------------------------------

    public function test_validation_halt_skips_all_later_stages_but_audits_once(): void
    {
        $log = [];
        $this->auditor = new SpyAuditor($log);
        $handlers = $this->defaultHandlers($log);
        $handlers[ActionPipelineStage::InputValidation->value] =
            FakeStageHandler::halting(ActionPipelineStage::InputValidation, $log, 'invalid_input');

        $outcome = $this->makeBus($handlers)->dispatch($this->makeCall());

        self::assertSame(['input_validation', 'audit'], $log);
        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::InputValidation, $outcome->haltedAt);
        self::assertSame('invalid_input', $outcome->halt->code);
        self::assertSame(1, $this->auditor->calls);
    }

    public function test_authorization_halt_skips_all_later_stages_but_audits_once(): void
    {
        $log = [];
        $this->auditor = new SpyAuditor($log);
        $handlers = $this->defaultHandlers($log);
        $handlers[ActionPipelineStage::Authorization->value] =
            FakeStageHandler::halting(ActionPipelineStage::Authorization, $log, 'denied');

        $outcome = $this->makeBus($handlers)->dispatch($this->makeCall());

        self::assertSame(['input_validation', 'authorization', 'audit'], $log);
        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Authorization, $outcome->haltedAt);
        self::assertSame('denied', $outcome->halt->code);
        self::assertSame(1, $this->auditor->calls);
    }

    // ------------------------------------------------------------------
    // Trusted context requirement check (built-in kernel step)
    // ------------------------------------------------------------------

    public function test_missing_context_requirement_halts_before_any_stage(): void
    {
        $log = [];
        $this->auditor = new SpyAuditor($log);
        $definition = $this->definition(requirements: [
            ContextRequirement::Tenant,
            ContextRequirement::CurrentSelection,
        ]);
        $registry = $this->registry($definition);

        // Caller input and metadata both carry the missing requirement's name;
        // neither may satisfy the trusted context check.
        $context = $this->context([
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
        ], metadata: ['current_selection' => [999]]);
        $call = new ActionCall($definition->id, $definition->version, [
            'current_selection' => [999],
        ], $context);

        $outcome = $this->makeBus($this->defaultHandlers($log), $registry)->dispatch($call);

        self::assertSame(['audit'], $log, 'No normal pipeline handler may run when trusted context is missing.');
        self::assertFalse($outcome->completed);
        self::assertNull($outcome->haltedAt, 'The kernel context check precedes all stages.');
        self::assertSame('required_context_missing', $outcome->halt->code);
        self::assertSame(['requirements' => ['current_selection']], $outcome->halt->details);
        self::assertSame(1, $this->auditor->calls);
    }

    public function test_context_requirements_satisfied_allow_pipeline_to_proceed(): void
    {
        $definition = $this->definition(requirements: [
            ContextRequirement::Tenant,
            ContextRequirement::CurrentSelection,
        ]);
        $context = $this->context([
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
            $this->entry(ContextRequirement::CurrentSelection, [10, 11]),
        ]);

        $outcome = $this->makeBus($this->defaultHandlers(), $this->registry($definition))
            ->dispatch(new ActionCall($definition->id, $definition->version, [], $context));

        self::assertTrue($outcome->completed);
    }

    public function test_empty_selection_entry_counts_as_present(): void
    {
        $definition = $this->definition(requirements: [ContextRequirement::CurrentSelection]);
        $context = $this->context([
            $this->entry(ContextRequirement::CurrentSelection, []),
        ]);

        $outcome = $this->makeBus($this->defaultHandlers(), $this->registry($definition))
            ->dispatch(new ActionCall($definition->id, $definition->version, [], $context));

        self::assertTrue($outcome->completed, 'Presence is not PHP truthiness; [] satisfies the presence check.');
    }

    // ------------------------------------------------------------------
    // Exact action resolution
    // ------------------------------------------------------------------

    public function test_missing_exact_identity_runs_no_stage_and_does_not_fallback(): void
    {
        $log = [];
        $registry = $this->registry($this->definition(id: 'foo.bar', version: 2));
        $bus = $this->makeBus($this->defaultHandlers($log), $registry);

        try {
            $bus->dispatch(new ActionCall('foo.bar', 1, [], $this->context([])));
            self::fail('Expected ActionDefinitionNotFound.');
        } catch (ActionDefinitionNotFound $exception) {
            self::assertSame('foo.bar', $exception->id);
            self::assertSame(1, $exception->version);
        }

        self::assertSame([], $log, 'No pipeline stage and no audit call on resolution failure.');
        self::assertSame(0, $this->auditor->calls);
    }

    // ------------------------------------------------------------------
    // Configuration / invariants
    // ------------------------------------------------------------------

    public function test_missing_required_stage_fails_at_construction(): void
    {
        $handlers = $this->defaultHandlers();
        unset($handlers[ActionPipelineStage::OutputPolicy->value]);

        $this->expectException(InvalidPipelineConfiguration::class);
        $this->expectExceptionMessage('output_policy');
        $this->makeBus($handlers);
    }

    public function test_duplicate_stage_handler_fails_at_construction(): void
    {
        $log = [];
        $handlers = $this->defaultHandlers();
        $handlers[] = new FakeStageHandler(ActionPipelineStage::Authorization, $log);

        $this->expectException(InvalidPipelineConfiguration::class);
        $this->expectExceptionMessage('more than one');
        $this->makeBus($handlers);
    }

    public function test_non_handler_pipeline_argument_fails_at_construction(): void
    {
        $this->expectException(InvalidPipelineConfiguration::class);
        $this->makeBus([$this->auditor]);
    }

    public function test_null_execution_output_is_a_legitimate_completed_result(): void
    {
        $log = [];
        $handlers = $this->defaultHandlers($log);
        $handlers[ActionPipelineStage::Execution->value] = new FakeStageHandler(
            ActionPipelineStage::Execution,
            $log,
            static fn (ActionPipelineState $state) => $state->withOutput(null),
        );

        $outcome = $this->makeBus($handlers)->dispatch($this->makeCall());

        self::assertTrue($outcome->completed);
        self::assertTrue($outcome->state->hasOutput);
        self::assertNull($outcome->state->output);
    }

    public function test_missing_execution_output_is_an_internal_invariant_violation(): void
    {
        $log = [];
        $handlers = $this->defaultHandlers($log);
        $handlers[ActionPipelineStage::Execution->value] =
            new FakeStageHandler(ActionPipelineStage::Execution, $log);

        $bus = $this->makeBus($handlers);

        $this->expectException(PipelineInvariantViolation::class);
        $this->expectExceptionMessage('execution output');
        $bus->dispatch($this->makeCall());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /**
     * @return array<string, FakeStageHandler>
     */
    private function defaultHandlers(array &$log = []): array
    {
        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = null;
            if ($stage === ActionPipelineStage::Execution) {
                $behavior = static fn (ActionPipelineState $state) => $state->withOutput('executed');
            }
            $handlers[$stage->value] = new FakeStageHandler($stage, $log, $behavior);
        }
        return $handlers;
    }

    /**
     * Deliberately shuffled injection order.
     *
     * @return list<FakeStageHandler>
     */
    private function shuffledHandlers(array &$log): array
    {
        return [
            new FakeStageHandler(ActionPipelineStage::Execution, $log,
                static fn (ActionPipelineState $state) => $state->withOutput('executed')),
            new FakeStageHandler(ActionPipelineStage::InputValidation, $log),
            new FakeStageHandler(ActionPipelineStage::OutputPolicy, $log),
            new FakeStageHandler(ActionPipelineStage::Authorization, $log),
            new FakeStageHandler(ActionPipelineStage::Confirmation, $log),
            new FakeStageHandler(ActionPipelineStage::Idempotency, $log),
        ];
    }

    private function makeBus(array $handlers, ?ActionRegistry $registry = null): ActionBus
    {
        return new ActionBus(
            $registry ?? $this->registry($this->definition()),
            $this->auditor,
            $handlers,
        );
    }

    private function registry(ActionDefinition $definition): ActionRegistry
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        return $registry;
    }

    private function definition(
        string $id = 'orders.refund.prepare',
        int $version = 1,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    private function makeCall(): ActionCall
    {
        return new ActionCall('orders.refund.prepare', 1, ['reason' => 'damaged'], $this->context([
            $this->entry(ContextRequirement::AuthenticatedActor, 'real-user'),
        ]));
    }

    private function context(array $entries, array $metadata = []): InvocationContext
    {
        return new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-1',
            trustedContext: $entries,
            metadata: $metadata,
        );
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry($requirement, $value, new ContextProvenance('test.resolver'));
    }
}

/**
 * Test fake: records stage execution into a shared log (by reference) and
 * optionally runs a behavior closure returning either an updated state
 * (continue) or a decision.
 */
final class FakeStageHandler implements ActionPipelineStageHandler
{
    private readonly ActionPipelineStage $stage;
    /** @var list<string> */
    private array $log;
    private readonly ?\Closure $behavior;

    /** @param list<string> $log */
    public function __construct(ActionPipelineStage $stage, array &$log, ?\Closure $behavior = null)
    {
        $this->stage = $stage;
        $this->log = &$log;
        $this->behavior = $behavior;
    }

    public static function halting(ActionPipelineStage $stage, array &$log, string $code): self
    {
        return new self(
            $stage,
            $log,
            static fn (ActionPipelineState $state) => ActionPipelineDecision::halt(new ActionPipelineHalt($code), $state),
        );
    }

    public function stage(): ActionPipelineStage
    {
        return $this->stage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->log[] = $this->stage->value;
        if ($this->behavior !== null) {
            $result = ($this->behavior)($state);
            if ($result instanceof ActionPipelineDecision) {
                return $result;
            }
            return ActionPipelineDecision::continueWith($result);
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class SpyAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;

    /** @var list<ActionPipelineOutcome> */
    public array $outcomes = [];

    /** @var list<string> */
    private array $log;

    /** @param list<string> $log */
    public function __construct(array &$log = [])
    {
        $this->log = &$log;
    }

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
        $this->outcomes[] = $outcome;
        $this->log[] = 'audit';
    }
}
