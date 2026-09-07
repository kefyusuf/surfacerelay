<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyPreflightResult
{
    private function __construct(
        public IdempotencyPreflightKind $kind,
        public mixed $output = null,
    ) {}

    public static function fresh(): self { return new self(IdempotencyPreflightKind::Fresh); }
    public static function replay(mixed $output): self { return new self(IdempotencyPreflightKind::Replay, $output); }
    public static function conflict(): self { return new self(IdempotencyPreflightKind::Conflict); }
    public static function inProgress(): self { return new self(IdempotencyPreflightKind::InProgress); }
    public static function indeterminate(): self { return new self(IdempotencyPreflightKind::Indeterminate); }
}
