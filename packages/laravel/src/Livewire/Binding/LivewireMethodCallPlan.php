<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Binding;

/** Driver-owned positional invocation plan for one exposed Livewire method. */
final readonly class LivewireMethodCallPlan
{
    /**
     * @param list<string> $inputOrder
     */
    public function __construct(
        public array $inputOrder,
        public int $requiredCount,
    ) {
        if (!array_is_list($this->inputOrder)) {
            throw new \InvalidArgumentException('Livewire call-plan inputOrder must be a list.');
        }

        $seen = [];
        foreach ($this->inputOrder as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Livewire call-plan inputOrder entries must be non-empty strings.');
            }
            if (isset($seen[$name])) {
                throw new \InvalidArgumentException('Livewire call-plan inputOrder entries must be unique.');
            }
            $seen[$name] = true;
        }

        if ($this->requiredCount < 0 || $this->requiredCount > count($this->inputOrder)) {
            throw new \InvalidArgumentException('Livewire call-plan requiredCount is outside inputOrder bounds.');
        }
    }
}
