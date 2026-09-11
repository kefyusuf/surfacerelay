<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Invocation;

use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Filament\Confirmation\FilamentConfirmationBridge;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentInvocationContextFactory;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;

/**
 * Trusted Filament-side gateway into the existing SurfaceRelay ActionBus.
 *
 * The caller supplies the exact active Filament Page instance and the normal
 * invocation envelope. This gateway derives trusted page context before
 * constructing ActionCall; it does not discover pages, resolve records by ID,
 * authorize by itself, or introduce a Filament RuntimeBinding driver.
 */
final readonly class FilamentActionGateway
{
    private FilamentInvocationContextFactory $contextFactory;

    public function __construct(
        private ActionBus $bus,
        TrustedContextComposer $baseComposer,
        int $maxSelectionRecords = FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS,
        private ?FilamentConfirmationBridge $confirmationBridge = null,
    ) {
        $this->contextFactory = new FilamentInvocationContextFactory(
            $baseComposer,
            maxSelectionRecords: $maxSelectionRecords,
        );
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $metadata
     */
    public function dispatch(
        Page $page,
        string $actionId,
        int $actionVersion,
        array $input,
        string $surface,
        string $correlationId,
        ?string $bindingId = null,
        ?string $confirmationReceipt = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
        ?FilamentContextExposure $contextExposure = null,
    ): ActionPipelineOutcome {
        $context = $this->contextFactory->forPage(
            page: $page,
            surface: $surface,
            correlationId: $correlationId,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            contextExposure: $contextExposure,
        );

        $outcome = $this->bus->dispatch(new ActionCall(
            actionId: $actionId,
            actionVersion: $actionVersion,
            input: $input,
            context: $context,
            bindingId: $bindingId,
            confirmationReceipt: $confirmationReceipt,
        ));

        $this->confirmationBridge?->presentIfRequired($page, $outcome);

        return $outcome;
    }
}
