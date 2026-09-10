<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Trusted Filament-side composition point for one invocation context.
 *
 * The exact active Page instance is supplied by trusted adapter code. This
 * factory does not discover pages, inspect routes/requests, resolve records by
 * ID, dispatch actions, or introduce a Filament execution driver.
 */
final readonly class FilamentInvocationContextFactory
{
    public function __construct(
        private TrustedContextComposer $baseComposer,
        private int $maxSelectionRecords = FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS,
    ) {
        if ($this->maxSelectionRecords < 1) {
            throw InvalidFilamentCurrentSelection::invalidConfiguration();
        }
    }

    /** @param array<string, mixed> $metadata */
    public function forPage(
        Page $page,
        string $surface,
        string $correlationId,
        ?string $idempotencyKey = null,
        array $metadata = [],
        ?FilamentContextExposure $contextExposure = null,
    ): InvocationContext {
        $trustedContext = (new FilamentTrustedContextComposer(
            $this->baseComposer,
            new FilamentRecordContextResolver($page),
            new FilamentCurrentSelectionResolver(
                $page,
                maxSelectionRecords: $this->maxSelectionRecords,
            ),
        ))->resolve();

        $context = new InvocationContext(
            surface: $surface,
            correlationId: $correlationId,
            trustedContext: $trustedContext,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );

        if ($contextExposure?->includesActiveFilters() !== true) {
            return $context;
        }

        $activeFilters = (new FilamentActiveFilterContextResolver($page))->resolve();

        return $context->withTrustedExtension(new TrustedContextExtension(
            key: FilamentActiveFilterContextResolver::EXTENSION_KEY,
            value: $activeFilters->value,
            provenance: $activeFilters->provenance,
            scopeKey: $activeFilters->confirmationScopeKey,
        ));
    }
}
