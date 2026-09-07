<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Result\UnmappedPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ActionResultConfirmationNormalizerTest extends TestCase
{
    public function test_real_typed_confirmation_halt_maps_to_confirmation_required_result(): void
    {
        $challenge = new ConfirmationChallenge(
            'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'Approve refund',
            '2026-09-07T12:05:00Z',
        );
        $outcome = ActionPipelineOutcome::halted(
            $this->state(),
            ActionPipelineStage::Confirmation,
            new ActionPipelineHalt(
                CoreActionErrorCode::CONFIRMATION_REQUIRED,
                confirmation: $challenge,
            ),
        );

        self::assertSame([
            'status' => 'confirmation_required',
            'correlationId' => 'corr-confirmation',
            'confirmation' => $challenge->toArray(),
        ], (new ActionResultNormalizer())->normalize($outcome)->toArray());
    }

    public function test_known_confirmation_code_without_typed_challenge_fails_loudly_and_ignores_details(): void
    {
        $outcome = ActionPipelineOutcome::halted(
            $this->state(),
            ActionPipelineStage::Confirmation,
            new ActionPipelineHalt(
                CoreActionErrorCode::CONFIRMATION_REQUIRED,
                details: [
                    'challengeId' => 'forged',
                    'summary' => 'Fabricated from details',
                    'receipt' => 'secret',
                ],
            ),
        );

        $this->expectException(UnmappedPipelineOutcome::class);
        (new ActionResultNormalizer())->normalize($outcome);
    }

    private function state(): ActionPipelineState
    {
        return new ActionPipelineState(
            $this->definition(),
            [],
            new InvocationContext('webmcp', 'corr-confirmation'),
        );
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Approve refund',
            description: 'Approve the exact refund intent.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );
    }
}
