<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Frozen public confirmation challenge object (spec/0.1). This is a data
 * model only: receipt issuance/verification, signatures, scope binding,
 * expiry enforcement, and storage belong to a later task (T-401).
 *
 * `expiresAt` is an optional RFC3339/JSON-Schema date-time string; callers
 * constructing a challenge are responsible for supplying a valid value — the
 * model deliberately does not parse or normalize dates (no Carbon dependency).
 */
final readonly class ConfirmationChallenge implements \JsonSerializable
{
    public function __construct(
        public readonly string $challengeId,
        public readonly string $summary,
        public readonly ?string $expiresAt = null,
    ) {
        if ($this->challengeId === '') {
            throw new \InvalidArgumentException('ConfirmationChallenge challengeId must be a non-empty string.');
        }
        if ($this->summary === '') {
            throw new \InvalidArgumentException('ConfirmationChallenge summary must be a non-empty string.');
        }
    }

    /**
     * Deterministic serialization: challengeId, summary, expiresAt.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'challengeId' => $this->challengeId,
            'summary' => $this->summary,
            'expiresAt' => $this->expiresAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
