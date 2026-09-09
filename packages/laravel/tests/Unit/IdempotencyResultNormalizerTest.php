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
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class IdempotencyResultNormalizerTest extends TestCase
{
    public function test_all_idempotency_refusals_normalize_to_static_rejected_results_without_details(): void
    {
        $normalizer = new ActionResultNormalizer();
        $messages = [
            CoreActionErrorCode::IDEMPOTENCY_KEY_REQUIRED => 'An idempotency key is required.',
            CoreActionErrorCode::IDEMPOTENCY_KEY_INVALID => 'The idempotency key is invalid.',
            CoreActionErrorCode::IDEMPOTENCY_CONFLICT => 'The idempotency key is already bound to a different invocation intent.',
            CoreActionErrorCode::IDEMPOTENCY_IN_PROGRESS => 'An invocation with this idempotency key is already in progress.',
            CoreActionErrorCode::IDEMPOTENCY_INDETERMINATE => 'The prior invocation outcome is indeterminate and will not be retried automatically.',
        ];

        foreach ($messages as $code => $message) {
            $outcome = ActionPipelineOutcome::halted(
                new ActionPipelineState(
                    $this->definition(),
                    [],
                    new InvocationContext('test', 'corr-idem-normalize'),
                ),
                ActionPipelineStage::Idempotency,
                new ActionPipelineHalt($code, [
                    'rawKey' => 'must-not-leak',
                    'keyHash' => str_repeat('a', 64),
                    'intentFingerprint' => str_repeat('b', 64),
                ]),
            );

            $result = $normalizer->normalize($outcome);

            self::assertSame([
                'status' => 'rejected',
                'correlationId' => 'corr-idem-normalize',
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ], $result->toArray(), $code . ' must expose no caller key or internal idempotency details.');
        }
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund',
            version: 1,
            title: 'Refund order',
            description: 'Refunds an exact order intent.',
            inputSchema: ['type' => 'object'],
            outputSchema: ['type' => 'object'],
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
