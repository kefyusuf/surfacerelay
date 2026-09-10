<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Filament\Tables\Contracts\HasTable;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

final class FilamentCurrentSelectionResolver
{
    public const int DEFAULT_MAX_SELECTION_RECORDS = 500;

    public function __construct(
        private readonly Page $page,
        private readonly int $maxSelectionRecords = self::DEFAULT_MAX_SELECTION_RECORDS,
    ) {
        if ($this->maxSelectionRecords < 1) {
            throw InvalidFilamentCurrentSelection::invalidConfiguration();
        }
    }

    public function resolve(): ?ResolvedTrustedValue
    {
        if (! $this->page instanceof HasTable) {
            return null;
        }

        return null;
    }
}
