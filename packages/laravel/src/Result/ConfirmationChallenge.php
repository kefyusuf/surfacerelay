<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Frozen public confirmation challenge object (spec/0.1). This is a data
 * model only: receipt issuance/verification, signatures, scope binding,
 * expiry enforcement, and storage belong to a later task (T-401).
 *
 * `expiresAt` is an optional RFC3339 date-time string (offset or `Z`, with
 * optional fractional seconds). Validation mirrors the repository's JSON
 * Schema format checker semantics; valid input is preserved verbatim and is
 * never normalized or re-formatted.
 */
final readonly class ConfirmationChallenge implements \JsonSerializable
{
    private const string RFC3339_PATTERN = '/^(?<year>\d{4})-(?<month>0[1-9]|1[0-2])-(?<day>\d{2})T(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d(?:\.\d+)?(?:Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/D';

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
        if ($this->expiresAt !== null && !self::isValidRfc3339($this->expiresAt)) {
            throw new \InvalidArgumentException(
                'ConfirmationChallenge expiresAt must be a valid RFC3339 date-time string.'
            );
        }
    }

    private static function isValidRfc3339(string $value): bool
    {
        if (preg_match(self::RFC3339_PATTERN, $value, $matches) !== 1) {
            return false;
        }

        $year = (int) $matches['year'];
        if ($year === 0) {
            return false;
        }

        return checkdate(
            (int) $matches['month'],
            (int) $matches['day'],
            $year,
        );
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
