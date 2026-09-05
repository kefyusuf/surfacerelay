<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Contracts\ActionRegistry;
use SurfaceRelay\Laravel\Registry\ActionDefinitionNotFound;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Protocol-neutral orchestration shell for one action invocation.
 *
 * Canonical flow per dispatch:
 *
 *     exact registry resolution (kernel)
 *     → trusted context requirement check (kernel)
 *     → input_validation → authorization → confirmation → idempotency
 *     → execution → output_policy
 *     → audit finalizer (exactly once, after the final outcome is known)
 *
 * Fail-closed rules: missing exact identity throws before any stage runs;
 * a missing trusted context requirement halts before any stage runs, with no
 * fallback to action input or metadata; a halted stage skips all later
 * normal stages while the audit finalizer still observes the outcome.
 * Handler injection order never determines execution order — canonical stage
 * order is imposed by the ActionPipelineStage enum. Unexpected stage
 * exceptions propagate unchanged (T-110 owns error normalization).
 */
final class ActionBus
{
    /** @var array<string, ActionPipelineStageHandler> keyed by stage value */
    private array $handlers = [];

    /**
     * @param list<ActionPipelineStageHandler> $handlers exactly one handler per
     * ActionPipelineStage; registration order is irrelevant, canonical order
     * is always imposed.
     */
    public function __construct(
        private readonly ActionRegistry $registry,
        private readonly ActionPipelineAuditor $auditor,
        array $handlers,
    ) {
        foreach ($handlers as $index => $handler) {
            if (!$handler instanceof ActionPipelineStageHandler) {
                throw InvalidPipelineConfiguration::invalidHandler($index);
            }
            $stage = $handler->stage()->value;
            if (isset($this->handlers[$stage])) {
                throw InvalidPipelineConfiguration::duplicateStage($handler->stage());
            }
            $this->handlers[$stage] = $handler;
        }
        foreach (ActionPipelineStage::cases() as $stage) {
            if (!isset($this->handlers[$stage->value])) {
                throw InvalidPipelineConfiguration::missingStage($stage);
            }
        }
    }

    /**
     * @param array<string, mixed> $input
     */
    public function dispatch(ActionCall $call): ActionPipelineOutcome
    {
        // Kernel step 1: exact identity resolution — never falls back to
        // another version. Failure throws before any stage executes.
        $definition = $this->registry->get($call->actionId, $call->actionVersion);

        $state = new ActionPipelineState($definition, $call->input, $call->context);

        // Kernel step 2: trusted context requirement check — presence only,
        // no domain validation, no fallback from input or metadata (D-007,
        // D-027). An empty selection entry counts as present.
        $missing = [];
        foreach ($definition->contextRequirements as $requirement) {
            if (!$call->context->has($requirement)) {
                $missing[] = $requirement->value;
            }
        }
        if ($missing !== []) {
            return $this->finalize($call, ActionPipelineOutcome::halted(
                $state,
                null,
                new ActionPipelineHalt(
                    CoreActionErrorCode::REQUIRED_CONTEXT_MISSING,
                    ['requirements' => $missing],
                ),
            ));
        }

        // Canonical stage order; a halt short-circuits all later stages.
        foreach (ActionPipelineStage::cases() as $stage) {
            $decision = $this->handlers[$stage->value]->process($state);
            if (!$decision->continue) {
                return $this->finalize($call, ActionPipelineOutcome::halted(
                    $decision->state,
                    $stage,
                    $decision->halt ?? new ActionPipelineHalt('halted'),
                ));
            }
            $state = $decision->state;
        }

        // Kernel invariant: execution must have occurred. null output is a
        // legitimate executed result, so presence — not truthiness — decides.
        if (!$state->hasOutput) {
            throw PipelineInvariantViolation::missingExecutionOutput();
        }

        return $this->finalize($call, ActionPipelineOutcome::completed($state));
    }

    private function finalize(ActionCall $call, ActionPipelineOutcome $outcome): ActionPipelineOutcome
    {
        $this->auditor->record($call, $outcome);
        return $outcome;
    }
}
