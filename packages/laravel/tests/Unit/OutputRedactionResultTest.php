<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\OutputPolicy\OutputRedactionResult;

final class OutputRedactionResultTest extends TestCase
{
    #[Test]
    public function released_null_is_distinct_from_withhold(): void
    {
        $released = OutputRedactionResult::release(null);
        $withheld = OutputRedactionResult::withhold();

        self::assertTrue($released->isReleased());
        self::assertNull($released->output());
        self::assertFalse($withheld->isReleased());
    }

    #[Test]
    public function withheld_output_cannot_be_read(): void
    {
        $withheld = OutputRedactionResult::withhold();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Withheld output is not releasable.');

        $withheld->output();
    }

    #[Test]
    public function released_values_are_returned_exactly(): void
    {
        $value = (object) ['safe' => true];
        $released = OutputRedactionResult::release($value);

        self::assertTrue($released->isReleased());
        self::assertSame($value, $released->output());
    }
}
