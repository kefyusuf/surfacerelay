<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Immutable per-invocation pipeline state. Trusted authority lives
 * exclusively in the InvocationContext; the state adds no authority-bearing
 * scratch bag. Output presence is tracked explicitly because null is a
 * legitimate execution result.
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
    ) {}

    /**
     * @param array<string, mixed> $input
     */
    public function withInput(array $input): self
    {
        return new self($this->definition, $input, $this->context, $this->hasOutput, $this->output);
    }

    public function withOutput(mixed $output): self
    {
        return new self($this->definition, $this->input, $this->context, true, $output);
    }
}
