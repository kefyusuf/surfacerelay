<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentCurrentSelectionResolver;

final class FilamentCurrentSelectionResolverTest extends TestCase
{
    public function test_default_selection_ceiling_is_500(): void
    {
        self::assertSame(500, FilamentCurrentSelectionResolver::DEFAULT_MAX_SELECTION_RECORDS);
    }
}
