<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
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
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class OutputPolicyPipelineIntegrationTest extends TestCase
{
    public function test_sensitive_release_sanitizes_completed_state_before_audit_and_public_result(): void
    {
        $rawMarker = 'raw-secret-output-marker';
        $definition = $this->definition(OutputContentTrust::ContainsUntrustedContent);
        $executor = new OutputPolicyIntegrationExecutor(['private' => $rawMarker]);
        $redactor = new OutputPolicyIntegrationRedactor(
            OutputRedactionResult::release(['orderId' => 1001, 'summary' => 'safe-summary']),
        );
        $auditor = new OutputPolicyIntegrationAuditor();
        $bus = $this->bus($definition, $executor, $redactor, $auditor);

        $outcome = $bus->dispatch($this->call($definition, 'corr-release'));
        $result = (new ActionResultNormalizer())->normalize($outcome);

        self::assertTrue($outcome->completed);
        self::assertSame(ActionPipelineStage::OutputPolicy, $redactor->lastStageBoundary);
        self::assertSame(1, $executor->calls);
        self::assertSame(1, $redactor->calls);
        self::assertSame(['private' => $rawMarker], $redactor->rawOutput);
        self::assertSame(['orderId' => 1001, 'summary' => 'safe-summary'], $outcome->state->output);
        self::assertSame(OutputContentTrust::ContainsUntrustedContent, $outcome->state->definition->outputContentTrust);

        self::assertSame(1, $auditor->calls);
        self::assertSame($outcome, $auditor->outcomes[0]);
        self::assertTrue($auditor->outcomes[0]->state->hasOutput);
        self::assertSame(['orderId' => 1001, 'summary' => 'safe-summary'], $auditor->outcomes[0]->state->output);

        self::assertSame('succeeded', $result->status->value);
        self::assertSame(['orderId' => 1001, 'summary' => 'safe-summary'], $result->data);
        self::assertStringNotContainsString($rawMarker, json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_sensitive_withhold_removes_raw_output_before_audit_and_returns_static_failed_result(): void
    {
        $rawMarker = 'raw-secret-withhold-marker';
        $definition = $this->definition();
        $executor = new OutputPolicyIntegrationExecutor(['private' => $rawMarker]);
        $redactor = new OutputPolicyIntegrationRedactor(OutputRedactionResult::withhold());
        $auditor = new OutputPolicyIntegrationAuditor();
        $bus = $this->bus($definition, $executor, $redactor, $auditor);

        $outcome = $bus->dispatch($this->call($definition, 'corr-withhold'));
        $result = (new ActionResultNormalizer())->normalize($outcome);

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::OutputPolicy, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $outcome->halt?->code);
        self::assertFalse($outcome->state->hasOutput);
        self::assertNull($outcome->state->output);
        self::assertSame(1, $executor->calls);
        self::assertSame(1, $redactor->calls);

        self::assertSame(1, $auditor->calls);
        self::assertSame($outcome, $auditor->outcomes[0]);
        self::assertFalse($auditor->outcomes[0]->state->hasOutput);
        self::assertNull($auditor->outcomes[0]->state->output);

        self::assertSame([
            'status' => 'failed',
            'correlationId' => 'corr-withhold',
            'error' => [
                'code' => 'output_policy_failed',
                'message' => 'The action completed, but its output could not be safely disclosed.',
            ],
        ], $result->toArray());
        self::assertStringNotContainsString($rawMarker, json_encode($result->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_redactor_exception_is_sanitized_before_audit_and_never_reaches_public_result(): void
    {
        $rawMarker = 'raw-secret-exception-output-marker';
        $exceptionMarker = 'redactor-secret-exception-marker';
        $definition = $this->definition();
        $executor = new OutputPolicyIntegrationExecutor(['private' => $rawMarker]);
        $redactor = new OutputPolicyIntegrationRedactor(
            failure: new \RuntimeException($exceptionMarker),
        );
        $auditor = new OutputPolicyIntegrationAuditor();
        $bus = $this->bus($definition, $executor, $redactor, $auditor);

        $outcome = $bus->dispatch($this->call($definition, 'corr-exception'));
        $result = (new ActionResultNormalizer())->normalize($outcome);
        $serialized = json_encode($result->toArray(), JSON_THROW_ON_ERROR);

        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::OutputPolicy, $outcome->haltedAt);
        self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $outcome->halt?->code);
        self::assertNull($outcome->halt?->details);
        self::assertFalse($outcome->state->hasOutput);
        self::assertNull($outcome->state->output);

        self::assertSame(1, $auditor->calls);
        self::assertFalse($auditor->outcomes[0]->state->hasOutput);
        self::assertNull($auditor->outcomes[0]->state->output);
        self::assertStringNotContainsString($rawMarker, $serialized);
        self::assertStringNotContainsString($exceptionMarker, $serialized);
        self::assertSame('failed', $result->status->value);
        self::assertSame(CoreActionErrorCode::OUTPUT_POLICY_FAILED, $result->error?->code);
        self::assertNull($result->error?->details);
    }

    private function bus(
        ActionDefinition $definition,
        ActionExecutor $executor,
        SensitiveOutputRedactor $redactor,
        ActionPipelineAuditor $auditor,
    ): ActionBus {
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        return new ActionBus(
            $registry,
            $auditor,
            [
                new OutputPolicyIntegrationPassThroughStage(ActionPipelineStage::InputValidation),
                new OutputPolicyIntegrationPassThroughStage(ActionPipelineStage::Authorization),
                new OutputPolicyIntegrationPassThroughStage(ActionPipelineStage::Idempotency),
                new OutputPolicyIntegrationPassThroughStage(ActionPipelineStage::Confirmation),
                new ActionExecutionStage($executor),
                new OutputPolicyStage($redactor),
            ],
        );
    }

    private function call(ActionDefinition $definition, string $correlationId): ActionCall
    {
        return new ActionCall(
            $definition->id,
            $definition->version,
            ['validated' => true],
            new InvocationContext('test', $correlationId),
        );
    }

    private function definition(
        OutputContentTrust $contentTrust = OutputContentTrust::TrustedApplicationData,
    ): ActionDefinition {
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
            outputContentTrust: $contentTrust,
            contextRequirements: [],
            outputSchema: ['type' => 'object'],
        );
    }
}

final class OutputPolicyIntegrationExecutor implements ActionExecutor
{
    public int $calls = 0;

    public function __construct(private readonly mixed $output) {}

    public function execute(
        ActionDefinition $definition,
        array $input,
        InvocationContext $context,
    ): mixed {
        ++$this->calls;

        return $this->output;
    }
}

final class OutputPolicyIntegrationRedactor implements SensitiveOutputRedactor
{
    public int $calls = 0;

    public mixed $rawOutput = null;

    public ?ActionPipelineStage $lastStageBoundary = null;

    public function __construct(
        private readonly ?OutputRedactionResult $result = null,
        private readonly ?\Throwable $failure = null,
    ) {}

    public function redact(
        ActionDefinition $definition,
        mixed $rawOutput,
        OutputPolicyContext $context,
    ): OutputRedactionResult {
        ++$this->calls;
        $this->rawOutput = $rawOutput;
        $this->lastStageBoundary = ActionPipelineStage::OutputPolicy;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->result ?? throw new \LogicException('Integration redactor requires a result or failure.');
    }
}

final class OutputPolicyIntegrationPassThroughStage implements ActionPipelineStageHandler
{
    public function __construct(private readonly ActionPipelineStage $pipelineStage) {}

    public function stage(): ActionPipelineStage
    {
        return $this->pipelineStage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        return ActionPipelineDecision::continueWith($state);
    }
}

final class OutputPolicyIntegrationAuditor implements ActionPipelineAuditor
{
    public int $calls = 0;

    /** @var list<ActionPipelineOutcome> */
    public array $outcomes = [];

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        ++$this->calls;
        $this->outcomes[] = $outcome;
    }
}
