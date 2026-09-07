<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

/**
 * Cache-safe server-side confirmation authority record.
 *
 * Raw bearer tokens are never stored here; callers address records only by a
 * one-way token hash through ConfirmationStore.
 */
final readonly class ConfirmationRecord
{
    public function __construct(
        public ConfirmationRecordState $state,
        public string $scopeFingerprint,
        public string $summary,
        public int $issuedAt,
        public int $challengeExpiresAt,
        public ?int $receiptExpiresAt = null,
    ) {
        if ($this->scopeFingerprint === '') {
            throw new \InvalidArgumentException('ConfirmationRecord scopeFingerprint must be non-empty.');
        }
        if ($this->summary === '') {
            throw new \InvalidArgumentException('ConfirmationRecord summary must be non-empty.');
        }
        if ($this->challengeExpiresAt <= $this->issuedAt) {
            throw new \InvalidArgumentException('ConfirmationRecord challenge expiry must be after issuance.');
        }
        if ($this->state === ConfirmationRecordState::Pending && $this->receiptExpiresAt !== null) {
            throw new \InvalidArgumentException('Pending confirmation record cannot have receipt expiry.');
        }
        if ($this->state === ConfirmationRecordState::Approved) {
            if ($this->receiptExpiresAt === null || $this->receiptExpiresAt <= $this->issuedAt) {
                throw new \InvalidArgumentException('Approved confirmation record requires a valid receipt expiry.');
            }
        }
    }

    /** @return array{state:string,scopeFingerprint:string,summary:string,issuedAt:int,challengeExpiresAt:int,receiptExpiresAt:int|null} */
    public function toArray(): array
    {
        return [
            'state' => $this->state->value,
            'scopeFingerprint' => $this->scopeFingerprint,
            'summary' => $this->summary,
            'issuedAt' => $this->issuedAt,
            'challengeExpiresAt' => $this->challengeExpiresAt,
            'receiptExpiresAt' => $this->receiptExpiresAt,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $required = ['state', 'scopeFingerprint', 'summary', 'issuedAt', 'challengeExpiresAt', 'receiptExpiresAt'];
        if (array_keys($data) !== $required) {
            throw new \UnexpectedValueException('Corrupt confirmation record shape.');
        }

        if (
            !is_string($data['state'])
            || !is_string($data['scopeFingerprint'])
            || !is_string($data['summary'])
            || !is_int($data['issuedAt'])
            || !is_int($data['challengeExpiresAt'])
            || !($data['receiptExpiresAt'] === null || is_int($data['receiptExpiresAt']))
        ) {
            throw new \UnexpectedValueException('Corrupt confirmation record value types.');
        }

        $state = ConfirmationRecordState::tryFrom($data['state']);
        if ($state === null) {
            throw new \UnexpectedValueException('Corrupt confirmation record state.');
        }

        try {
            return new self(
                state: $state,
                scopeFingerprint: $data['scopeFingerprint'],
                summary: $data['summary'],
                issuedAt: $data['issuedAt'],
                challengeExpiresAt: $data['challengeExpiresAt'],
                receiptExpiresAt: $data['receiptExpiresAt'],
            );
        } catch (\InvalidArgumentException $exception) {
            throw new \UnexpectedValueException('Corrupt confirmation record invariants.', previous: $exception);
        }
    }
}
