<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Confirmation;

use Livewire\Attributes\Locked;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

trait InteractsWithSurfaceRelayConfirmation
{
    #[Locked]
    public ?string $surfaceRelayConfirmationChallengeId = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationSummary = null;

    #[Locked]
    public ?string $surfaceRelayConfirmationExpiresAt = null;

    public function presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void
    {
        $this->surfaceRelayConfirmationChallengeId = $challenge->challengeId;
        $this->surfaceRelayConfirmationSummary = $challenge->summary;
        $this->surfaceRelayConfirmationExpiresAt = $challenge->expiresAt;
    }
}
