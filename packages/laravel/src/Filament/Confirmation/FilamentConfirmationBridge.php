<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Confirmation;

use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;

final readonly class FilamentConfirmationBridge
{
    public function presentIfRequired(Page $page, ActionPipelineOutcome $outcome): void
    {
        if ($outcome->completed) {
            return;
        }

        $halt = $outcome->halt;
        $looksLikeConfirmation = $outcome->haltedAt === ActionPipelineStage::Confirmation
            || $halt?->code === CoreActionErrorCode::CONFIRMATION_REQUIRED
            || $halt?->confirmation !== null;

        if (!$looksLikeConfirmation) {
            return;
        }

        if (
            $outcome->haltedAt !== ActionPipelineStage::Confirmation
            || $halt === null
            || $halt->code !== CoreActionErrorCode::CONFIRMATION_REQUIRED
            || !$halt->confirmation instanceof ConfirmationChallenge
        ) {
            throw InvalidFilamentConfirmationBridge::invalidOutcome();
        }

        if (!in_array(InteractsWithSurfaceRelayConfirmation::class, class_uses_recursive($page), true)) {
            throw InvalidFilamentConfirmationBridge::hostUnavailable();
        }

        $page->presentSurfaceRelayConfirmation($halt->confirmation);
    }
}
