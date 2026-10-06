<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/** Atomic server-side storage contract for one opaque confirmation token hash. */
interface ConfirmationStore
{
    public function createPending(string $tokenHash, ConfirmationRecord $record, int $ttlSeconds): bool;

    /**
     * Atomically moves an approvable pending record from the challenge hash to a
     * fresh receipt hash, so the challenge id never becomes receipt authority.
     * Returns false without mutation when the challenge is not approvable or the
     * receipt hash is already occupied.
     */
    public function approvePending(string $tokenHash, string $receiptHash, int $now, int $receiptExpiresAt): bool;

    public function consumeApproved(string $tokenHash, string $expectedScopeFingerprint, int $now): bool;
}
