<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;

final readonly class FilamentTrustedContextComposer
{
    public function __construct(
        private TrustedContextComposer $baseComposer,
        private FilamentRecordContextResolver $recordResolver,
        private ?FilamentCurrentSelectionResolver $selectionResolver = null,
    ) {}

    /** @return list<TrustedContextEntry> */
    public function resolve(): array
    {
        $entries = $this->baseComposer->resolve();
        $record = $this->recordResolver->resolve();

        if ($record !== null) {
            $entries[] = new TrustedContextEntry(
                requirement: ContextRequirement::CurrentRecord,
                value: $record->value,
                provenance: $record->provenance,
                confirmationScopeKey: $record->confirmationScopeKey,
            );
        }

        $selection = $this->selectionResolver?->resolve();

        if ($selection !== null) {
            $entries[] = new TrustedContextEntry(
                requirement: ContextRequirement::CurrentSelection,
                value: $selection->value,
                provenance: $selection->provenance,
                confirmationScopeKey: $selection->confirmationScopeKey,
            );
        }

        return $entries;
    }
}
