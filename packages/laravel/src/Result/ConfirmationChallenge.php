<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Frozen public confirmation challenge object (spec/0.1). This is a data
 * model only: receipt issuance/verification, signatures, scope binding,
 * expiry enforcement, and storage belong to a later task (T-401).
 *
 * `expiresAt` is an optional RFC3339 date-time string (offset or `Z`, with
 * optional fractional seconds). Values are validated deterministically with
 * native PHP (no Carbon); valid input is preserved verbatim — never
 * normalized or re-formatted.
 */
final readonly class ConfirmationChallenge implements \JsonSerializable
{
    private const string RFC3339_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/';

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
        if (preg_match(self::RFC3339_PATTERN, $value) !== 1) {
            return false;
        }

        // Reject syntactically plausible but impossible dates (month 13, etc.).
        $hasFraction = str_contains($value, '.');
        $endsWithZ = str_ends_with($value, 'Z');
        $format = match (true) {
            $hasFraction && $endsWithZ => 'Y-m-d\TH:i:s.u\Z',
            $hasFraction => 'Y-m-d\TH:i:s.uP',
            $endsWithZ => 'Y-m-d\TH:i:s\Z',
            default => 'Y-m-d\TH:i:sP',
        };

        $parsed = \DateTimeImmutable::createFromFormat($format, $value);
        if ($parsed === false) {
            return false;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) {
            return false;
        }

        return true;
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
