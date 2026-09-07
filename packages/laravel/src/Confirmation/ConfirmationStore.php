<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Atomic server-side storage contract for one opaque confirmation token hash. */
interface ConfirmationStore
{
    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool;

    public function approvePending(string $tokenHash, int $now, int $receiptExpiresAt): bool;

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool;
}
