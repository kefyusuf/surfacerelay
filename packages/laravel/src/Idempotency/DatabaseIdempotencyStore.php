<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;

/** Durable database-backed idempotency state with short atomic claim/transition transactions. */
final readonly class DatabaseIdempotencyStore implements IdempotencyStore
{
    private const int MAX_CLAIM_ATTEMPTS = 3;

    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'surfacerelay_idempotency_records',
    ) {}

    public function find(string $keyHash): ?IdempotencyRecord
    {
        try {
            $row = $this->connection->table($this->table)
                ->where('key_hash', $keyHash)
                ->first();
        } catch (QueryException) {
            throw IdempotencyStoreUnavailable::operationFailed();
        }

        return $row === null ? null : $this->hydrate($row);
    }

    public function claim(IdempotencyRecord $fresh, int $now): IdempotencyStoreClaimResult
    {
        for ($attempt = 0; $attempt < self::MAX_CLAIM_ATTEMPTS; $attempt++) {
            try {
                $this->connection->table($this->table)->insert($this->toRow($fresh));

                return IdempotencyStoreClaimResult::claimed($fresh);
            } catch (UniqueConstraintViolationException) {
                // A key already exists. Resolve it under a short row-lock transaction below.
            } catch (QueryException) {
                throw IdempotencyStoreUnavailable::operationFailed();
            }

            try {
                $result = $this->connection->transaction(function () use ($fresh, $now): ?IdempotencyStoreClaimResult {
                    $row = $this->connection->table($this->table)
                        ->where('key_hash', $fresh->keyHash)
                        ->lockForUpdate()
                        ->first();

                    if ($row === null) {
                        return null;
                    }

                    $existing = $this->hydrate($row);
                    if ($existing->isActiveAt($now)) {
                        return IdempotencyStoreClaimResult::existing($existing);
                    }

                    $updated = $this->connection->table($this->table)
                        ->where('key_hash', $fresh->keyHash)
                        ->update($this->toRow($fresh));

                    if ($updated !== 1) {
                        return null;
                    }

                    return IdempotencyStoreClaimResult::claimed($fresh);
                });
            } catch (QueryException) {
                throw IdempotencyStoreUnavailable::operationFailed();
            }

            if ($result !== null) {
                return $result;
            }
        }

        throw IdempotencyStoreUnavailable::claimContention();
    }

    public function complete(string $keyHash, string $intentFingerprint, string $outputPayload): void
    {
        try {
            $updated = $this->connection->table($this->table)
                ->where('key_hash', $keyHash)
                ->where('intent_fingerprint', $intentFingerprint)
                ->where('state', IdempotencyRecordState::InProgress->value)
                ->update([
                    'state' => IdempotencyRecordState::Completed->value,
                    'output_payload' => $outputPayload,
                ]);
        } catch (QueryException) {
            throw IdempotencyStoreUnavailable::operationFailed();
        }

        if ($updated !== 1) {
            throw IdempotencyStoreUnavailable::transitionFailed();
        }
    }

    public function markIndeterminate(string $keyHash, string $intentFingerprint): void
    {
        try {
            $updated = $this->connection->table($this->table)
                ->where('key_hash', $keyHash)
                ->where('intent_fingerprint', $intentFingerprint)
                ->where('state', IdempotencyRecordState::InProgress->value)
                ->update([
                    'state' => IdempotencyRecordState::Indeterminate->value,
                    'output_payload' => null,
                ]);
        } catch (QueryException) {
            throw IdempotencyStoreUnavailable::operationFailed();
        }

        if ($updated !== 1) {
            throw IdempotencyStoreUnavailable::transitionFailed();
        }
    }

    /** @return array{key_hash:string,intent_fingerprint:string,state:string,output_payload:string|null,created_at:string,expires_at:string} */
    private function toRow(IdempotencyRecord $record): array
    {
        return [
            'key_hash' => $record->keyHash,
            'intent_fingerprint' => $record->intentFingerprint,
            'state' => $record->state->value,
            'output_payload' => $record->outputPayload,
            'created_at' => $this->formatTimestamp($record->createdAt),
            'expires_at' => $this->formatTimestamp($record->expiresAt),
        ];
    }

    private function hydrate(object $row): IdempotencyRecord
    {
        foreach (['key_hash', 'intent_fingerprint', 'state', 'output_payload', 'created_at', 'expires_at'] as $field) {
            if (!property_exists($row, $field)) {
                throw CorruptIdempotencyRecord::persistedShape();
            }
        }

        if (
            !is_string($row->key_hash)
            || !is_string($row->intent_fingerprint)
            || !is_string($row->state)
            || !($row->output_payload === null || is_string($row->output_payload))
            || !is_string($row->created_at)
            || !is_string($row->expires_at)
        ) {
            throw CorruptIdempotencyRecord::persistedShape();
        }

        $state = IdempotencyRecordState::tryFrom($row->state);
        if ($state === null) {
            throw CorruptIdempotencyRecord::invalidState();
        }

        return new IdempotencyRecord(
            keyHash: $row->key_hash,
            intentFingerprint: $row->intent_fingerprint,
            state: $state,
            outputPayload: $row->output_payload,
            createdAt: $this->parseTimestamp($row->created_at),
            expiresAt: $this->parseTimestamp($row->expires_at),
        );
    }

    private function formatTimestamp(int $timestamp): string
    {
        return gmdate('Y-m-d H:i:s', $timestamp);
    }

    private function parseTimestamp(string $value): int
    {
        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d H:i:s',
            $value,
            new DateTimeZone('UTC'),
        );
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $date === false
            || ($errors !== false && ($errors['warning_count'] !== 0 || $errors['error_count'] !== 0))
            || $date->format('Y-m-d H:i:s') !== $value
        ) {
            throw CorruptIdempotencyRecord::invalidTimestamp();
        }

        return $date->getTimestamp();
    }
}
