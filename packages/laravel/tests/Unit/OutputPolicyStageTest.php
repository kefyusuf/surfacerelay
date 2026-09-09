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
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyContext;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputRedactionResult;
use SurfaceRelay\Laravel\OutputPolicy\SensitiveOutputRedactor;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class OutputPolicyStageTest extends TestCase
{
    public function test_normal_output_passes_through_exactly_without_invoking_sensitive_redactor(): void
    {
        $redactor = new OutputPolicyStageRedactorProbe(OutputRedactionResult::release(['should' => 'not-run']));
        $stage = new OutputPolicyStage($redactor);
        $output = (object) ['public' => true];
        $state = (new ActionPipelineState(
            $this->definition(OutputSensitivity::Normal),
            ['validated' => true],
            new InvocationContext('test', 'corr-normal-policy'),
        ))->withOutput($output);

        $decision = $stage->process($state);

        self::assertSame(ActionPipelineStage::OutputPolicy, $stage->stage());
        self::assertTrue($decision->continue);
        self::assertSame($state, $decision->state);
        self::assertTrue($decision->state->hasOutput);
        self::assertSame($output, $decision->state->output);
        self::assertSame(0, $redactor->calls);
    }

    private function definition(OutputSensitivity $sensitivity): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.show',
            version: 1,
            title: 'Show order',
            description: 'Returns one order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Routine,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: $sensitivity,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [],
            outputSchema: ['type' => 'object'],
        );
    }
}

final class OutputPolicyStageRedactorProbe implements SensitiveOutputRedactor
{
    public int $calls = 0;

    public function __construct(private readonly OutputRedactionResult $result) {}

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        ++$this->calls;

        return $this->result;
    }
}
