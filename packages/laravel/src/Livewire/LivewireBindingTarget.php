<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire;

/** Driver-owned execution target for one exact mounted Livewire component. */
final readonly class LivewireBindingTarget implements \JsonSerializable
{
    /** @var list<string> */
    public array $inputOrder;

    public int $requiredCount;

    private bool $hasCallPlan;

    /**
     * @param list<string>|null $inputOrder
     */
    public function __construct(
        public string $componentId,
        public string $method,
        ?array $inputOrder = null,
        ?int $requiredCount = null,
    ) {
        if ($this->componentId === '') {
            throw new \InvalidArgumentException('Livewire binding componentId must be a non-empty string.');
        }
        if ($this->method === '') {
            throw new \InvalidArgumentException('Livewire binding method must be a non-empty string.');
        }
        if (($inputOrder === null) !== ($requiredCount === null)) {
            throw new \InvalidArgumentException('Livewire binding call plan requires both inputOrder and requiredCount.');
        }

        $this->hasCallPlan = $inputOrder !== null;
        $this->inputOrder = $inputOrder ?? [];
        $this->requiredCount = $requiredCount ?? 0;

        if (!array_is_list($this->inputOrder)) {
            throw new \InvalidArgumentException('Livewire binding inputOrder must be a list.');
        }

        $seen = [];
        foreach ($this->inputOrder as $name) {
            if (!is_string($name) || $name === '') {
                throw new \InvalidArgumentException('Livewire binding inputOrder entries must be non-empty strings.');
            }
            if (isset($seen[$name])) {
                throw new \InvalidArgumentException('Livewire binding inputOrder entries must be unique.');
            }
            $seen[$name] = true;
        }

        if ($this->requiredCount < 0 || $this->requiredCount > count($this->inputOrder)) {
            throw new \InvalidArgumentException('Livewire binding requiredCount is outside inputOrder bounds.');
        }
    }

    /**
     * @return array{componentId: string, method: string}|array{componentId: string, method: string, inputOrder: list<string>, requiredCount: int}
     */
    public function toArray(): array
    {
        $target = [
            'componentId' => $this->componentId,
            'method' => $this->method,
        ];

        if ($this->hasCallPlan) {
            $target['inputOrder'] = $this->inputOrder;
            $target['requiredCount'] = $this->requiredCount;
        }

        return $target;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
