<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

final class PrepListStore
{
    /** @var list<array{itemId: string, name: string}> */
    private array $items = [];
    private int $nextId = 1;

    /** @return array{itemId: string, name: string} */
    public function add(string $name): array
    {
        $item = [
            'itemId' => 'item-' . $this->nextId++,
            'name' => $name,
        ];
        $this->items[] = $item;

        return $item;
    }

    /** @return list<array{itemId: string, name: string}> */
    public function all(): array
    {
        return $this->items;
    }
}
