<?php

declare(strict_types=1);

namespace App\Filament\Resources\A2Orders\Pages;

use App\A2\InspectOrder;
use App\Filament\Resources\A2Orders\A2OrderResource;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Locked;
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

/**
 * B2: the page additionally issues the app-owned browser descriptor document
 * (canonical definition JSON plus the exact producer-issued component binding).
 * The descriptor is public discovery metadata and never an authorization
 * capability: every dispatch still re-resolves actor, tenant, record and
 * binding server-side. Browser registration semantics are B2/A2-08 evidence.
 */
final class ListA2Orders extends ListRecords
{
    protected static string $resource = A2OrderResource::class;

    #[Locked]
    public string $inspectionStatus = 'idle';

    #[Locked]
    public ?array $inspected = null;

    /** Server-issued descriptor document; Locked because the browser must not mutate it. */
    #[Locked]
    public ?array $surfacerelayDescriptor = null;

    #[ExposeAction('a2.orders.inspect', 1)]
    public function inspectOrder(int $order_id): void
    {
        $outcome = app(InspectOrder::class)->dispatch($this, ['order_id' => $order_id]);
        $this->inspectionStatus = $outcome->completed ? 'ok' : 'denied';
        $this->inspected = $outcome->completed ? $outcome->state->output : null;
    }

    public function render()
    {
        $this->issueBrowserDescriptor();

        return parent::render();
    }

    private function issueBrowserDescriptor(): void
    {
        if ($this->surfacerelayDescriptor !== null) {
            return;
        }

        $definition = InspectOrder::definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $bindings = (new LivewireBindingProducer(
            new LivewireActionExposureReader($registry),
            new MethodLivewireComponentIdentityResolver(),
            new RandomBindingIdGenerator(),
        ))->forComponent($this);

        $binding = $bindings[0] ?? null;
        if ($binding === null) {
            return;
        }

        $this->surfacerelayDescriptor = [
            'definition' => [
                'id' => $definition->id,
                'version' => $definition->version,
                'title' => $definition->title,
                'description' => $definition->description,
                'inputSchema' => $definition->inputSchema,
                'outputSchema' => $definition->outputSchema,
                'scope' => $definition->scope->value,
                'effect' => $definition->effect->value,
                'risk' => $definition->risk->value,
                'idempotency' => $definition->idempotency->value,
                'outputSensitivity' => $definition->outputSensitivity->value,
                'outputContentTrust' => $definition->outputContentTrust->value,
                'contextRequirements' => array_map(
                    static fn (ContextRequirement $requirement): string => $requirement->value,
                    $definition->contextRequirements,
                ),
            ],
            'binding' => $binding->toArray(),
        ];
    }
}
