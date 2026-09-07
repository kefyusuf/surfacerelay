<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

interface IdempotencyStore
{
    public function find(string $keyHash): ?IdempotencyRecord;
    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult;
    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void;
    public function markIndeterminate(string $keyHash, string $intentFingerprint): void;
}
