<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Smoke;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;

final class EnumVocabularyTest extends TestCase
{
    /**
     * Exhaustive stable-vocabulary assertions. Ordering is normative where
     * the runtime relies on canonical declaration order (ContextRequirement).
     */
    public function test_action_scope_values_are_stable(): void
    {
        self::assertSame(
            ['portable', 'page_scoped', 'browser_local', 'headless'],
            array_map(static fn (ActionScope $case): string => $case->value, ActionScope::cases()),
        );
    }

    public function test_action_effect_values_are_stable(): void
    {
        self::assertSame(
            ['read', 'reversible_write', 'destructive_write', 'external_side_effect'],
            array_map(static fn (ActionEffect $case): string => $case->value, ActionEffect::cases()),
        );
    }

    public function test_action_risk_values_are_stable(): void
    {
        self::assertSame(
            ['low', 'moderate', 'high', 'consequential'],
            array_map(static fn (ActionRisk $case): string => $case->value, ActionRisk::cases()),
        );
    }

    public function test_idempotency_policy_values_are_stable(): void
    {
        self::assertSame(
            ['none', 'recommended_key', 'required_key'],
            array_map(static fn (IdempotencyPolicy $case): string => $case->value, IdempotencyPolicy::cases()),
        );
    }

    public function test_output_sensitivity_values_are_stable(): void
    {
        self::assertSame(
            ['normal', 'sensitive'],
            array_map(static fn (OutputSensitivity $case): string => $case->value, OutputSensitivity::cases()),
        );
    }

    public function test_output_content_trust_values_are_stable(): void
    {
        self::assertSame(
            ['trusted_application_data', 'contains_untrusted_content'],
            array_map(static fn (OutputContentTrust $case): string => $case->value, OutputContentTrust::cases()),
        );
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
