<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire;

/** Driver-owned execution target for one exact mounted Livewire component. */
final readonly class LivewireBindingTarget implements \JsonSerializable
{
    /**
     * @param list<string> $inputOrder
     */
    public function __construct(
        public string $componentId,
        public string $method,
        public array $inputOrder = [],
        public int $requiredCount = 0,
    ) {
        if ($this->componentId === '') {
            throw new \InvalidArgumentException('Livewire binding componentId must be a non-empty string.');
        }
        if ($this->method === '') {
            throw new \InvalidArgumentException('Livewire binding method must be a non-empty string.');
        }
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
     * @return array{componentId: string, method: string, inputOrder: list<string>, requiredCount: int}
     */
    public function toArray(): array
    {
        return [
            'componentId' => $this->componentId,
            'method' => $this->method,
            'inputOrder' => $this->inputOrder,
            'requiredCount' => $this->requiredCount,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
