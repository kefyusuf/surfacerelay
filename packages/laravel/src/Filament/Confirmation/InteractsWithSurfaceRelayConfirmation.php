<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Confirmation;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

trait InteractsWithSurfaceRelayConfirmation
{
    private const string SURFACE_RELAY_CONFIRMATION_ACTION = 'surfacerelay_confirmation';

    private const string APPROVED_NOTIFICATION_TITLE = 'Confirmation approved. Retry the original operation.';

    private const string RETRY_NOTIFICATION_TITLE = 'Confirmation is no longer approvable. Retry the original operation.';

    private const string APPROVED_RECEIPT_SESSION_PREFIX = 'surfacerelay.filament.confirmation.approved.';

    #[Locked]
    public ?string $surfaceRelayConfirmationChallengeId = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationSummary = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationExpiresAt = null;

    public function surfacerelay_confirmationAction(): Action
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
            ->action(function (): void {
                $this->approveSurfaceRelayConfirmation();
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

        $this->forgetApprovedSurfaceRelayConfirmationReceipt();

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

    protected function resolveSurfaceRelayConfirmationService(): ConfirmationService
    {
        if (!app()->bound(ConfirmationService::class)) {
            throw InvalidFilamentConfirmationBridge::serviceUnavailable();
        }

        $service = app(ConfirmationService::class);
        if (!$service instanceof ConfirmationService) {
            throw InvalidFilamentConfirmationBridge::serviceUnavailable();
        }

        return $service;
    }

    private function approveSurfaceRelayConfirmation(): void
    {
        $challengeId = $this->surfaceRelayConfirmationChallengeId;
        if ($challengeId === null || $this->surfaceRelayConfirmationSummary === null) {
            throw InvalidFilamentConfirmationBridge::presentationFailed();
        }

        $service = $this->resolveSurfaceRelayConfirmationService();

        try {
            $receipt = $service->approveChallenge($challengeId);
        } catch (\Throwable) {
            throw InvalidFilamentConfirmationBridge::approvalFailed();
        }

        $this->clearSurfaceRelayConfirmation();

        if ($receipt === null) {
            Notification::make()
                ->warning()
                ->title(self::RETRY_NOTIFICATION_TITLE)
                ->send();

            return;
        }

        $this->rememberApprovedSurfaceRelayConfirmationReceipt($receipt);

        Notification::make()
            ->success()
            ->title(self::APPROVED_NOTIFICATION_TITLE)
            ->send();
    }

    /**
     * Removes and returns the receipt this exact component approved. The
     * receipt lives only in server-side session state, never in public
     * component state, and is not Livewire-callable; the page's own retry
     * passes it to the gateway, which still verifies the exact scope. Session
     * pull is not an atomic concurrency guard; store consumption governs reuse.
     */
    protected function pullApprovedSurfaceRelayConfirmationReceipt(): ?string
    {
        $key = $this->approvedSurfaceRelayConfirmationReceiptKey();
        if ($key === null) {
            return null;
        }

        $receipt = session()->pull($key);

        return is_string($receipt) ? $receipt : null;
    }

    // Without a component identity a receipt cannot be bound to the approving
    // page, so none is kept and no retry capability exists.
    private function rememberApprovedSurfaceRelayConfirmationReceipt(string $receipt): void
    {
        $key = $this->approvedSurfaceRelayConfirmationReceiptKey();
        if ($key !== null) {
            session()->put($key, $receipt);
        }
    }

    private function forgetApprovedSurfaceRelayConfirmationReceipt(): void
    {
        $key = $this->approvedSurfaceRelayConfirmationReceiptKey();
        if ($key !== null) {
            session()->forget($key);
        }
    }

    private function approvedSurfaceRelayConfirmationReceiptKey(): ?string
    {
        $componentId = $this->getId();
        if (!is_string($componentId) || $componentId === '') {
            return null;
        }

        return self::APPROVED_RECEIPT_SESSION_PREFIX . hash('sha256', $componentId);
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
