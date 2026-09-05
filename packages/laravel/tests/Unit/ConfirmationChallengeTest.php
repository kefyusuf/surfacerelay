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

    public function test_valid_rfc3339_forms_are_preserved_verbatim(): void
    {
        $zulu = new ConfirmationChallenge('c1', 's', '2026-09-06T10:30:00Z');
        $offset = new ConfirmationChallenge('c2', 's', '2026-09-06T13:30:00+03:00');

        self::assertSame('2026-09-06T10:30:00Z', $zulu->expiresAt);
        self::assertSame('2026-09-06T13:30:00+03:00', $offset->expiresAt);
    }

    public function test_natural_language_dates_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RFC3339');
        new ConfirmationChallenge('c1', 's', 'tomorrow afternoon');
    }

    public function test_impossible_calendar_dates_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RFC3339');
        new ConfirmationChallenge('c1', 's', '2026-13-40T25:61:61Z');
    }

    public function test_missing_timezone_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('RFC3339');
        new ConfirmationChallenge('c1', 's', '2026-09-06T10:30:00');
    }
}
