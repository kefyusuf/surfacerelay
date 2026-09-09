<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyContext;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputRedactionResult;
use SurfaceRelay\Laravel\OutputPolicy\SensitiveOutputRedactor;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
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

    public function test_sensitive_release_replaces_raw_output_and_receives_only_trusted_policy_context(): void
    {
        $actor = new TrustedContextEntry(
            ContextRequirement::AuthenticatedActor,
            (object) ['id' => 42],
            new ContextProvenance('test.actor'),
            'actor:42',
        );
        $tenant = new TrustedContextEntry(
            ContextRequirement::Tenant,
            (object) ['id' => 7],
            new ContextProvenance('test.tenant'),
            'tenant:7',
        );
        $definition = $this->definition(OutputSensitivity::Sensitive);
        $rawOutput = (object) ['private' => 'raw-secret'];
        $safeOutput = ['orderId' => 1001, 'status' => 'ready'];
        $redactor = new OutputPolicyStageRedactorProbe(OutputRedactionResult::release($safeOutput));
        $stage = new OutputPolicyStage($redactor);
        $state = (new ActionPipelineState(
            $definition,
            ['validated' => true],
            new InvocationContext(
                surface: 'webmcp',
                correlationId: 'corr-sensitive-release',
                trustedContext: [$tenant, $actor],
                idempotencyKey: 'raw-retry-key',
                metadata: [
                    'role' => 'admin',
                    'tenant' => 'caller-tenant',
                    'secret' => 'caller-metadata',
                ],
            ),
        ))->withOutput($rawOutput);

        $decision = $stage->process($state);

        self::assertTrue($decision->continue);
        self::assertTrue($decision->state->hasOutput);
        self::assertSame($safeOutput, $decision->state->output);
        self::assertSame(1, $redactor->calls);
        self::assertSame($definition, $redactor->definition);
        self::assertSame($rawOutput, $redactor->rawOutput);
        self::assertInstanceOf(OutputPolicyContext::class, $redactor->context);
        self::assertSame([$actor, $tenant], $redactor->context->allTrusted());
        self::assertTrue($redactor->context->has(ContextRequirement::AuthenticatedActor));
        self::assertTrue($redactor->context->has(ContextRequirement::Tenant));
        self::assertFalse($redactor->context->has(ContextRequirement::BrowserSession));
    }

    public function test_sensitive_release_of_null_remains_a_successful_output(): void
    {
        $redactor = new OutputPolicyStageRedactorProbe(OutputRedactionResult::release(null));
        $stage = new OutputPolicyStage($redactor);
        $state = (new ActionPipelineState(
            $this->definition(OutputSensitivity::Sensitive),
            ['validated' => true],
            new InvocationContext('test', 'corr-sensitive-null'),
        ))->withOutput(['private' => 'raw-secret']);

        $decision = $stage->process($state);

        self::assertTrue($decision->continue);
        self::assertTrue($decision->state->hasOutput);
        self::assertNull($decision->state->output);
        self::assertSame(1, $redactor->calls);
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
            risk: ActionRisk::Low,
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

    public ?ActionDefinition $definition = null;

    public mixed $rawOutput = null;

    public ?OutputPolicyContext $context = null;

    public function __construct(private readonly OutputRedactionResult $result) {}

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        ++$this->calls;
        $this->definition = $definition;
        $this->rawOutput = $rawOutput;
        $this->context = $context;

        return $this->result;
    }
}
