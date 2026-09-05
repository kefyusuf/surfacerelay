<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Result\ActionError;

final class ActionErrorTest extends TestCase
{
    public function test_valid_code_and_message_are_preserved_verbatim(): void
    {
        $error = new ActionError('input_validation_failed', 'Input validation failed.', ['fields' => ['email']]);

        self::assertSame('input_validation_failed', $error->code);
        self::assertSame('Input validation failed.', $error->message);
        self::assertSame(['fields' => ['email']], $error->details);
    }

    public function test_empty_code_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('code');
        new ActionError('', 'Message');
    }

    public function test_empty_message_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('message');
        new ActionError('authorization_denied', '');
    }

    public function test_details_are_preserved_verbatim_without_normalization(): void
    {
        $error = new ActionError('some_code', 'Message', '  padded  ');

        self::assertSame('  padded  ', $error->details);
    }

    public function test_deterministic_serialization_without_details_key_when_absent(): void
    {
        $withDetails = (new ActionError('c', 'm', ['k' => 'v']))->toArray();
        $withoutDetails = (new ActionError('c', 'm'))->toArray();

        self::assertSame(['code' => 'c', 'message' => 'm', 'details' => ['k' => 'v']], $withDetails);
        self::assertSame(['code' => 'c', 'message' => 'm'], $withoutDetails);
        self::assertArrayNotHasKey('details', $withoutDetails);
    }
}
