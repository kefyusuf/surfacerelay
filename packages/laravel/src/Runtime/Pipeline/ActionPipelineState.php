<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Idempotency\IdempotencyExecutionPlan;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Immutable per-invocation pipeline state. Trusted authority lives
 * exclusively in the InvocationContext; binding identity and confirmation
 * receipt are invocation candidates/references and never become authority by
 * presence alone. Output presence is tracked explicitly because null is a
 * legitimate execution result. Idempotency plans are runtime-owned execution
 * coordination state and never caller authority.
 */
final readonly class ActionPipelineState
{
    /**
     * @param array<string, mixed> $input
     */
    public function __construct(
        public ActionDefinition $definition,
        public array $input,
        public InvocationContext $context,
        public bool $hasOutput = false,
        public mixed $output = null,
        public ?string $bindingId = null,
        public ?string $confirmationReceipt = null,
        public ?IdempotencyExecutionPlan $idempotencyPlan = null,
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        return new self(
            $this->definition,
            $input,
            $this->context,
            $this->hasOutput,
            $this->output,
            $this->bindingId,
            $this->confirmationReceipt,
            $this->idempotencyPlan,
        );
    }

    public function withOutput(mixed $output): self
    {
        return new self(
            $this->definition,
            $this->input,
            $this->context,
            true,
            $output,
            $this->bindingId,
            $this->confirmationReceipt,
            $this->idempotencyPlan,
        );
    }

    public function withContext(InvocationContext $context): self
    {
        return new self(
            $this->definition,
            $this->input,
            $context,
            $this->hasOutput,
            $this->output,
            $this->bindingId,
            $this->confirmationReceipt,
            $this->idempotencyPlan,
        );
    }

    public function withIdempotencyPlan(IdempotencyExecutionPlan $plan): self
    {
        return new self(
            $this->definition,
            $this->input,
            $this->context,
            $this->hasOutput,
            $this->output,
            $this->bindingId,
            $this->confirmationReceipt,
            $plan,
        );
    }
}
