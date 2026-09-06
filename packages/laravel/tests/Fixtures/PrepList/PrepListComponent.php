<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use Livewire\Component;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;

final class PrepListComponent extends Component
{
    protected PrepListActionGateway $actions;

    public function boot(PrepListActionGateway $actions): void
    {
        $this->actions = $actions;
    }

    /** @return array{itemId: string, name: string} */
    #[ExposeAction(id: 'prep_list.add_item', version: 1)]
    public function addItem(string $name): array
    {
        return $this->actions->addItem($name);
    }

    public function render(): string
    {
        return '<div>Prep list</div>';
    }
}
