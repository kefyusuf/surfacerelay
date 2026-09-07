<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyExecutionPlan
{
    private function __construct(
        public IdempotencyExecutionPlanKind $kind,
        public ?string $keyHash = null,
        public ?string $intentFingerprint = null,
        public mixed $replayOutput = null,
    ) {}

    public static function bypass(): self
    {
        return new self(IdempotencyExecutionPlanKind::Bypass);
    }

    public static function fresh(string $keyHash, string $intentFingerprint): self
    {
        return new self(IdempotencyExecutionPlanKind::FreshAttempt, $keyHash, $intentFingerprint);
    }

    public static function replay(string $keyHash, string $intentFingerprint, mixed $output): self
    {
        return new self(IdempotencyExecutionPlanKind::Replay, $keyHash, $intentFingerprint, $output);
    }
}
