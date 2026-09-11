<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Confirmation;

use Filament\Actions\Action;
use Livewire\Attributes\Locked;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

trait InteractsWithSurfaceRelayConfirmation
{
    private const string SURFACE_RELAY_CONFIRMATION_ACTION = 'surfacerelay_confirmation';

    #[Locked]
    public ?string $surfaceRelayConfirmationChallengeId = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationSummary = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationExpiresAt = null;

    public function surfaceRelayConfirmationAction(): Action
    {
        return Action::make(self::SURFACE_RELAY_CONFIRMATION_ACTION)
            ->requiresConfirmation()
            ->modalHeading('Confirm action')
            ->modalDescription(fn (): string => $this->surfaceRelayConfirmationDescription())
            ->modalSubmitActionLabel('Approve')
            ->modalCancelActionLabel('Cancel')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false)
            ->modalCloseButton(false)
            ->disabled(fn (): bool => $this->surfaceRelayConfirmationChallengeId === null)
            ->action(static function (): void {
                throw InvalidFilamentConfirmationBridge::presentationFailed();
            })
            ->modalCancelAction(fn (Action $action): Action => $action
                ->action(function (): void {
                    $this->clearSurfaceRelayConfirmation();
                })
                ->close());
    }

    public function presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void
    {
        $hasId = $this->surfaceRelayConfirmationChallengeId !== null;
        $hasSummary = $this->surfaceRelayConfirmationSummary !== null;
        $hasExpiry = $this->surfaceRelayConfirmationExpiresAt !== null;

        if ($hasId !== $hasSummary || (!$hasId && $hasExpiry)) {
            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }

        $mountedAction = $this->getMountedAction();

        if ($hasId) {
            if (
                $this->surfaceRelayConfirmationChallengeId !== $challenge->challengeId
                || $this->surfaceRelayConfirmationSummary !== $challenge->summary
                || $this->surfaceRelayConfirmationExpiresAt !== $challenge->expiresAt
            ) {
                throw InvalidFilamentConfirmationBridge::presentationConflict();
            }

            if ($mountedAction?->getName() === self::SURFACE_RELAY_CONFIRMATION_ACTION) {
                return;
            }

            if ($mountedAction !== null) {
                throw InvalidFilamentConfirmationBridge::presentationConflict();
            }

            $this->mountSurfaceRelayConfirmationAction();

            return;
        }

        if ($mountedAction !== null) {
            throw InvalidFilamentConfirmationBridge::presentationConflict();
        }

        $this->surfaceRelayConfirmationChallengeId = $challenge->challengeId;
        $this->surfaceRelayConfirmationSummary = $challenge->summary;
        $this->surfaceRelayConfirmationExpiresAt = $challenge->expiresAt;

        try {
            $this->mountSurfaceRelayConfirmationAction();
        } catch (InvalidFilamentConfirmationBridge $exception) {
            $this->clearSurfaceRelayConfirmation();

            throw $exception;
        } catch (\Throwable) {
            $this->clearSurfaceRelayConfirmation();

            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }
    }

    public function clearSurfaceRelayConfirmation(): void
    {
        $this->surfaceRelayConfirmationChallengeId = null;
        $this->surfaceRelayConfirmationSummary = null;
        $this->surfaceRelayConfirmationExpiresAt = null;
    }

    private function mountSurfaceRelayConfirmationAction(): void
    {
        try {
            $this->mountAction(self::SURFACE_RELAY_CONFIRMATION_ACTION);
            $mountedAction = $this->getMountedAction();
        } catch (\Throwable) {
            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }

        if ($mountedAction?->getName() !== self::SURFACE_RELAY_CONFIRMATION_ACTION) {
            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }
    }

    private function surfaceRelayConfirmationDescription(): string
    {
        $summary = $this->surfaceRelayConfirmationSummary;
        if ($this->surfaceRelayConfirmationChallengeId === null || $summary === null) {
            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }

        if ($this->surfaceRelayConfirmationExpiresAt === null) {
            return $summary;
        }

        return $summary . ' Expires at ' . $this->surfaceRelayConfirmationExpiresAt;
    }
}
