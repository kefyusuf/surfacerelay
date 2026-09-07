<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Confirmation;

use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

/**
 * Issues, approves and consumes opaque single-use confirmation capabilities.
 * All authority semantics live in the server-side ConfirmationStore; bearer
 * tokens are non-self-describing and are addressed only by SHA-256 hashes.
 */
final readonly class ConfirmationService
{
    private const string TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';
    private const int MAX_CREATE_ATTEMPTS = 3;

    public function __construct(
        private ConfirmationStore $store,
        private ConfirmationClock $clock,
        private ConfirmationTokenGenerator $tokenGenerator,
        private int $challengeTtlSeconds = 300,
        private int $receiptTtlSeconds = 120,
    ) {
        if ($this->challengeTtlSeconds <= 0) {
            throw new \InvalidArgumentException('Confirmation challenge TTL must be a positive integer.');
        }
        if ($this->receiptTtlSeconds <= 0) {
            throw new \InvalidArgumentException('Confirmation receipt TTL must be a positive integer.');
        }
    }

    public function issueChallenge(string $scopeFingerprint, string $summary): ConfirmationChallenge
    {
        if ($scopeFingerprint === '') {
            throw new \InvalidArgumentException('Confirmation scope fingerprint must be non-empty.');
        }
        if ($summary === '') {
            throw new \InvalidArgumentException('Confirmation summary must be non-empty.');
        }

        $now = $this->clock->now();
        $challengeExpiresAt = $now + $this->challengeTtlSeconds;

        for ($attempt = 0; $attempt < self::MAX_CREATE_ATTEMPTS; $attempt++) {
            $token = $this->tokenGenerator->generate();
            if (!$this->isOpaqueToken($token)) {
                throw ConfirmationTokenGenerationFailed::invalidGeneratorOutput();
            }

            $record = new ConfirmationRecord(
                state: ConfirmationRecordState::Pending,
                scopeFingerprint: $scopeFingerprint,
                summary: $summary,
                issuedAt: $now,
                challengeExpiresAt: $challengeExpiresAt,
            );

            if ($this->store->createPending(
                $this->tokenHash($token),
                $record,
                $this->challengeTtlSeconds,
            )) {
                return new ConfirmationChallenge(
                    challengeId: $token,
                    summary: $summary,
                    expiresAt: gmdate('Y-m-d\TH:i:s\Z', $challengeExpiresAt),
                );
            }
        }

        throw ConfirmationTokenGenerationFailed::afterCollisions();
    }

    public function approveChallenge(string $challengeId): ?string
    {
        if (!$this->isOpaqueToken($challengeId)) {
            return null;
        }

        $now = $this->clock->now();
        if (!$this->store->approvePending(
            $this->tokenHash($challengeId),
            $now,
            $now + $this->receiptTtlSeconds,
        )) {
            return null;
        }

        return $challengeId;
    }

    public function consumeReceipt(string $candidate, string $scopeFingerprint): bool
    {
        if (!$this->isOpaqueToken($candidate)) {
            return false;
        }
        if ($scopeFingerprint === '') {
            throw new \InvalidArgumentException('Confirmation scope fingerprint must be non-empty.');
        }

        return $this->store->consumeApproved(
            $this->tokenHash($candidate),
            $scopeFingerprint,
            $this->clock->now(),
        );
    }

    private function isOpaqueToken(string $value): bool
    {
        return preg_match(self::TOKEN_PATTERN, $value) === 1;
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }
}
