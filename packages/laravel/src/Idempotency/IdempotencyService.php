<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

final readonly class IdempotencyService
{
    public function __construct(
        private IdempotencyStore $store,
        private IdempotencyClock $clock,
        private IdempotencyReplayCodec $codec,
        private int $retentionSeconds = 86400,
    ) {
        if ($this->retentionSeconds < 1) {
            throw IdempotencyConfigurationViolation::invalidRetention();
        }
    }

    public function preflight(string $keyHash, string $intentFingerprint): IdempotencyPreflightResult
    {
        $record = $this->store->find($keyHash);
        if ($record === null || !$record->isActiveAt($this->clock->now())) {
            return IdempotencyPreflightResult::fresh();
        }

        if ($record->intentFingerprint !== $intentFingerprint) {
            return IdempotencyPreflightResult::conflict();
        }

        return match ($record->state) {
            IdempotencyRecordState::Completed => IdempotencyPreflightResult::replay(
                $this->codec->decode($record->outputPayload ?? throw CorruptIdempotencyRecord::completedWithoutOutput()),
            ),
            IdempotencyRecordState::InProgress => IdempotencyPreflightResult::inProgress(),
            IdempotencyRecordState::Indeterminate => IdempotencyPreflightResult::indeterminate(),
        };
    }

    public function claim(IdempotencyExecutionPlan $plan): IdempotencyClaimResult
    {
        $this->requireFresh($plan);
        $now = $this->clock->now();
        $fresh = new IdempotencyRecord(
            $plan->keyHash ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
            $plan->intentFingerprint ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
            IdempotencyRecordState::InProgress,
            null,
            $now,
            $now + $this->retentionSeconds,
        );

        $result = $this->store->claim($fresh, $now);
        if ($result->claimed) {
            return IdempotencyClaimResult::claimed();
        }

        $record = $result->record;
        if ($record->intentFingerprint !== $fresh->intentFingerprint) {
            return IdempotencyClaimResult::conflict();
        }

        return match ($record->state) {
            IdempotencyRecordState::Completed => IdempotencyClaimResult::replay(
                $this->codec->decode($record->outputPayload ?? throw CorruptIdempotencyRecord::completedWithoutOutput()),
            ),
            IdempotencyRecordState::InProgress => IdempotencyClaimResult::inProgress(),
            IdempotencyRecordState::Indeterminate => IdempotencyClaimResult::indeterminate(),
        };
    }

    public function complete(IdempotencyExecutionPlan $plan, mixed $output): void
    {
        $this->requireFresh($plan);
        $this->store->complete(
            $plan->keyHash ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
            $plan->intentFingerprint ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
            $this->codec->encode($output),
        );
    }

    public function markIndeterminate(IdempotencyExecutionPlan $plan): void
    {
        $this->requireFresh($plan);
        $this->store->markIndeterminate(
            $plan->keyHash ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
            $plan->intentFingerprint ?? throw IdempotencyConfigurationViolation::freshPlanRequired(),
        );
    }

    private function requireFresh(IdempotencyExecutionPlan $plan): void
    {
        if ($plan->kind !== IdempotencyExecutionPlanKind::FreshAttempt) {
            throw IdempotencyConfigurationViolation::freshPlanRequired();
        }
    }
}
