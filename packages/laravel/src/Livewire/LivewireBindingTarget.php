<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire;

/**
 * Driver-owned target data for one exact mounted Livewire component instance.
 *
 * T-201 validates descriptor shape only. Component existence, exposure,
 * lifecycle validity, and method execution belong to later tasks.
 */
final readonly class LivewireBindingTarget implements \JsonSerializable
{
    public function __construct(
        public string $componentId,
        public string $method,
    ) {
        if ($this->componentId === '') {
            throw new \InvalidArgumentException('Livewire binding componentId must be a non-empty string.');
        }
        if ($this->method === '') {
            throw new \InvalidArgumentException('Livewire binding method must be a non-empty string.');
        }
    }

    /** @return array{componentId: string, method: string} */
    public function toArray(): array
    {
        return [
            'componentId' => $this->componentId,
            'method' => $this->method,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
