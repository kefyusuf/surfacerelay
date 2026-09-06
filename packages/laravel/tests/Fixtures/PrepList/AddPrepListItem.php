<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

final class AddPrepListItem
{
    public int $calls = 0;

    public function __construct(
        private readonly PrepListStore $store,
    ) {}

    /** @return array{itemId: string, name: string} */
    public function handle(string $name): array
    {
        ++$this->calls;
        return $this->store->add($name);
    }
}
