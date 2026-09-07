<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyStoreClaimResult
{
    private function __construct(
        public bool $claimed,
        public IdempotencyRecord $record,
    ) {}

    public static function claimed(IdempotencyRecord $record): self
    {
        return new self(true, $record);
    }

    public static function existing(IdempotencyRecord $record): self
    {
        return new self(false, $record);
    }
}
