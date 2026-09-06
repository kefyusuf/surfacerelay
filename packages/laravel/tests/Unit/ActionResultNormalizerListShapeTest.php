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
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ActionResultNormalizerListShapeTest extends TestCase
{
    public function test_associative_core_detail_list_is_dropped(): void
    {
        $definition = new ActionDefinition(
            id: 'review.test',
            version: 1,
            title: 'Review test',
            description: 'Exercises result normalization detail shape.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::Portable,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
        );

        $state = new ActionPipelineState(
            $definition,
            [],
            new InvocationContext('test', 'corr-associative-details'),
        );

        $outcome = ActionPipelineOutcome::halted(
            $state,
            null,
            new ActionPipelineHalt(
                CoreActionErrorCode::REQUIRED_CONTEXT_MISSING,
                ['requirements' => ['secret-key' => 'tenant']],
            ),
        );

        $result = (new ActionResultNormalizer())->normalize($outcome);

        self::assertNull(
            $result->error?->details,
            'Associative arrays are not list<string> and must never be normalized into public details.',
        );
    }
}
