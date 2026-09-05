<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

final class ConfirmationChallengeTest extends TestCase
{
    public function test_valid_challenge_is_preserved(): void
    {
        $challenge = new ConfirmationChallenge('challenge-1', 'Approve refund', '2026-09-07T00:00:00Z');

        self::assertSame('challenge-1', $challenge->challengeId);
        self::assertSame('Approve refund', $challenge->summary);
        self::assertSame('2026-09-07T00:00:00Z', $challenge->expiresAt);
    }

    public function test_challenge_with_null_expiry_is_valid(): void
    {
        $challenge = new ConfirmationChallenge('challenge-1', 'Approve refund');

        self::assertNull($challenge->expiresAt);
        self::assertSame([
            'challengeId' => 'challenge-1',
            'summary' => 'Approve refund',
            'expiresAt' => null,
        ], $challenge->toArray());
    }

    public function test_empty_challenge_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('challengeId');
        new ConfirmationChallenge('', 'Approve refund');
    }

    public function test_empty_summary_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('summary');
        new ConfirmationChallenge('challenge-1', '');
    }
}
