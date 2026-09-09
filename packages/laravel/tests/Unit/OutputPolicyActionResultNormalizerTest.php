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

final class OutputPolicyActionResultNormalizerTest extends TestCase
{
    public function test_output_policy_failure_maps_to_static_failed_result_without_halt_details(): void
    {
        $state = (new ActionPipelineState(
            $this->definition(),
            ['validated' => true],
            new InvocationContext('test', 'corr-output-policy'),
        ))->withOutput(['private' => 'raw-secret-output-marker'])->withoutOutput();

        $outcome = ActionPipelineOutcome::halted(
            $state,
            ActionPipelineStage::OutputPolicy,
            new ActionPipelineHalt(CoreActionErrorCode::OUTPUT_POLICY_FAILED, [
                'raw' => 'raw-secret-output-marker',
                'exception' => 'redactor-secret-exception-marker',
                'retrySafe' => true,
            ]),
        );

        $result = (new ActionResultNormalizer())->normalize($outcome);

        self::assertSame([
            'status' => 'failed',
            'correlationId' => 'corr-output-policy',
            'error' => [
                'code' => 'output_policy_failed',
                'message' => 'The action completed, but its output could not be safely disclosed.',
            ],
        ], $result->toArray());
        self::assertNull($result->error?->details);
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.private_summary',
            version: 1,
            title: 'Private order summary',
            description: 'Returns a sensitive order summary.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: ['type' => 'object'],
        );
    }
}
