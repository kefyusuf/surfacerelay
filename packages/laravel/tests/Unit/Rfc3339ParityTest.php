<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Binding\BindingLifecycle;
use SurfaceRelay\Laravel\Binding\RuntimeBinding;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

final class Rfc3339ParityTest extends TestCase
{
    private const string LONG_FRACTION = '2001-10-23T15:32:12.9023368Z';

    public function test_confirmation_challenge_accepts_schema_valid_long_fractional_seconds(): void
    {
        $challenge = new ConfirmationChallenge('challenge-1', 'Approve', self::LONG_FRACTION);
        self::assertSame(self::LONG_FRACTION, $challenge->expiresAt);
    }

    public function test_runtime_binding_accepts_schema_valid_long_fractional_seconds(): void
    {
        $binding = $this->binding(self::LONG_FRACTION);
        self::assertSame(self::LONG_FRACTION, $binding->expiresAt);
    }

    #[DataProvider('schemaInvalidDateTimes')]
    public function test_confirmation_challenge_rejects_values_rejected_by_schema_checker(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ConfirmationChallenge('challenge-1', 'Approve', $value);
    }

    #[DataProvider('schemaInvalidDateTimes')]
    public function test_runtime_binding_rejects_values_rejected_by_schema_checker(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->binding($value);
    }

    public static function schemaInvalidDateTimes(): iterable
    {
        yield 'year zero' => ['0000-01-01T00:00:00Z'];
        yield 'offset hour 24' => ['2026-01-01T00:00:00+24:00'];
        yield 'offset minute 60' => ['2026-01-01T00:00:00+03:60'];
    }

    private function binding(string $expiresAt): RuntimeBinding
    {
        return new RuntimeBinding(
            bindingId: 'binding-rfc3339',
            definition: $this->definition(),
            driver: 'livewire',
            lifecycle: BindingLifecycle::Component,
            target: ['componentId' => 'cmp-1'],
            expiresAt: $expiresAt,
        );
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Refund order',
            description: 'Refunds an order through application logic.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
