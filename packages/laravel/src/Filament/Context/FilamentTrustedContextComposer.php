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
    ) {}

    /** @return list<TrustedContextEntry> */
    public function resolve(): array
    {
        $entries = $this->baseComposer->resolve();
        $record = $this->recordResolver->resolve();

        if ($record === null) {
            return $entries;
        }

        $entries[] = new TrustedContextEntry(
            requirement: ContextRequirement::CurrentRecord,
            value: $record->value,
            provenance: $record->provenance,
            confirmationScopeKey: $record->confirmationScopeKey,
        );

        return $entries;
    }
}
