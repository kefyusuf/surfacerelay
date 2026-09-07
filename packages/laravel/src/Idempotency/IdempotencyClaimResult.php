<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyClaimResult
{
    private function __construct(
        public IdempotencyClaimKind $kind,
        public mixed $output = null,
    ) {}

    public static function claimed(): self { return new self(IdempotencyClaimKind::Claimed); }
    public static function replay(mixed $output): self { return new self(IdempotencyClaimKind::Replay, $output); }
    public static function conflict(): self { return new self(IdempotencyClaimKind::Conflict); }
    public static function inProgress(): self { return new self(IdempotencyClaimKind::InProgress); }
    public static function indeterminate(): self { return new self(IdempotencyClaimKind::Indeterminate); }
}
