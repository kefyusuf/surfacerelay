<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ContextRequirement;

final class EnumVocabularyTest extends TestCase
{
    public function test_protocol_neutral_values_are_stable(): void
    {
        self::assertSame('read', ActionEffect::Read->value);
        self::assertSame('consequential', ActionRisk::Consequential->value);
    }

    public function test_context_requirement_values_match_frozen_v0_1_vocabulary(): void
    {
        self::assertSame(
            [
                'authenticated_actor',
                'tenant',
                'current_record',
                'current_selection',
                'browser_session',
                'human_confirmation',
            ],
            array_map(
                static fn (ContextRequirement $requirement): string => $requirement->value,
                ContextRequirement::cases(),
            ),
        );
    }
}
