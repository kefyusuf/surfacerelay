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
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResult;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\ActionResultStatus;
use SurfaceRelay\Laravel\Result\ActionError;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Result\UnmappedPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt as PipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final class ActionResultNormalizerTest extends TestCase
{
    private ActionResultNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new ActionResultNormalizer();
    }

    // ------------------------------------------------------------------
    // §18/§32 — success
    // ------------------------------------------------------------------

    public function test_completed_outcome_maps_to_succeeded_without_context_projection(): void
    {
        $context = $this->context([
            $this->entry(ContextRequirement::AuthenticatedActor, 'real-user'),
            $this->entry(ContextRequirement::Tenant, (object) ['name' => 'Acme']),
        ]);
        $outcome = ActionPipelineOutcome::completed(
            new ActionPipelineState($this->definition(), ['amount' => 100], $context, true, 'result-data'),
        );

        $result = $this->normalizer->normalize($outcome);

        self::assertSame([
            'status' => 'succeeded',
            'correlationId' => 'corr-123',
            'data' => 'result-data',
        ], $result->toArray(), 'No actor/tenant/context data may be auto-projected into the result.');
    }

    public function test_successful_null_output_serializes_with_data_present(): void
    {
        $outcome = ActionPipelineOutcome::completed(
            new ActionPipelineState($this->definition(), [], $this->context([]), true, null),
        );

        $result = $this->normalizer->normalize($outcome);

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertSame([
            'status' => 'succeeded',
            'correlationId' => 'corr-123',
            'data' => null,
        ], $result->toArray(), 'Presence matters: data key stays even when null.');    }

    // ------------------------------------------------------------------
    // §33 — validation rejection via real T-107 stage
    // ------------------------------------------------------------------

    public function test_validation_halt_normalizes_to_rejected_with_safe_field_details(): void
    {
        $validatorFactory = new \Illuminate\Validation\Factory(
            new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en'),
        );
        $definition = $this->definition();
        $rules = new InMemoryActionValidationRules();
        $rules->register($definition, [
            'email' => ['required', 'email'],
            'age' => ['required', 'integer', 'min:0'],
        ]);
        $stage = new LaravelInputValidationStage($validatorFactory, $rules);

        $decision = $stage->process(new ActionPipelineState(
            $definition,
            ['email' => 'not-an-email', 'age' => 'old-enough-to-vote', 'extra' => 'value'],
            $this->context([]),
        ));

        $outcome = ActionPipelineOutcome::halted($decision->state, ActionPipelineStage::InputValidation, $decision->halt);
        $result = $this->normalizer->normalize($outcome);

        self::assertSame([
            'status' => 'rejected',
            'correlationId' => 'corr-123',
            'error' => [
                'code' => 'input_validation_failed',
                'message' => 'Input validation failed.',
                'details' => ['fields' => ['age', 'email']],
            ],
        ], $result->toArray(), 'Deterministic lexical field order; no rejected values, no Laravel messages.');
    }

    // ------------------------------------------------------------------
    // §34 — authorization rejection via T-109 stage behavior
    // ------------------------------------------------------------------

    public function test_authorization_halt_normalizes_to_rejected_without_gate_details(): void
    {
        $outcome = ActionPipelineOutcome::halted(
            $this->stateWithContextActor(),
            ActionPipelineStage::Authorization,
            new PipelineHalt(CoreActionErrorCode::AUTHORIZATION_DENIED),
        );

        $result = $this->normalizer->normalize($outcome);

        self::assertSame([
            'status' => 'rejected',
            'correlationId' => 'corr-123',
            'error' => [
                'code' => 'authorization_denied',
                'message' => 'Authorization denied.',
            ],
        ], $result->toArray(), 'No Gate denial text, no actor object, no details.');
    }

    // ------------------------------------------------------------------
    // §35 — missing context via real ActionBus kernel gate
    // ------------------------------------------------------------------

    public function test_missing_context_halt_normalizes_to_rejected_with_requirement_identifiers_only(): void
    {
        $definition = $this->definition(requirements: [
            ContextRequirement::Tenant,
            ContextRequirement::CurrentSelection,
        ]);
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $bus = new \SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus(
            $registry,
            new FakeOutcomeAuditor(),
            $this->fakeHandlers(),
        );

        // Caller input and metadata both carry the missing requirement names.
        $context = new InvocationContext(
            'test',
            'corr-123',
            [$this->entry(ContextRequirement::Tenant, 'tenant-A')],
            metadata: ['current_selection' => [999]],
        );
        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['current_selection' => [999]],
            $context,
        ));

        $result = $this->normalizer->normalize($outcome);

        self::assertSame([
            'status' => 'rejected',
            'correlationId' => 'corr-123',
            'error' => [
                'code' => 'required_context_missing',
                'message' => 'Required trusted context is unavailable.',
                'details' => ['requirements' => ['current_selection']],
            ],
        ], $result->toArray(), 'Only missing requirement identifiers in canonical order; no fallback values.');
    }

    // ------------------------------------------------------------------
    // §20/§36 — unknown halt codes fail loudly
    // ------------------------------------------------------------------

    public function test_unknown_halt_code_is_never_silently_classified(): void
    {
        $outcome = ActionPipelineOutcome::halted(
            $this->stateWithContextActor(),
            ActionPipelineStage::Confirmation,
            new PipelineHalt('future_feature_required'),
        );

        $this->expectException(UnmappedPipelineOutcome::class);
        $this->expectExceptionMessage('future_feature_required');
        $this->normalizer->normalize($outcome);
    }

    public function test_failed_factory_is_available_but_not_wired_to_exceptions(): void
    {
        // §22: the model exists; broad Throwable normalization is deliberately
        // out of scope, so configuration/runtime exceptions keep propagating.
        $result = ActionResult::failed(
            'corr-9',
            new ActionError('execution_failed', 'Execution failed.'),
        );

        self::assertSame([
            'status' => 'failed',
            'correlationId' => 'corr-9',
            'error' => ['code' => 'execution_failed', 'message' => 'Execution failed.'],
        ], $result->toArray());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stateWithContextActor(): ActionPipelineState
    {
        return new ActionPipelineState(
            $this->definition(),
            [],
            $this->context([$this->entry(ContextRequirement::AuthenticatedActor, 'real-user')]),
            true,
            'out',
        );
    }

    private function context(array $entries): InvocationContext
    {
        return new InvocationContext('test', 'corr-123', $entries);
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry($requirement, $value, new ContextProvenance('test.resolver'));
    }

    /**
     * @param list<ContextRequirement> $requirements
     */
    private function definition(array $requirements = []): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: $requirements,
        );
    }

    /**
     * @return list<\SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler>
     */
    private function fakeHandlers(): array
    {
        return array_map(
            static fn (ActionPipelineStage $stage): \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler => new class($stage) implements \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler {
                public function __construct(private readonly ActionPipelineStage $stage) {}

                public function stage(): ActionPipelineStage
                {
                    return $this->stage;
                }

                public function process(ActionPipelineState $state): ActionPipelineDecision
                {
                    if ($this->stage === ActionPipelineStage::Execution) {
                        return ActionPipelineDecision::continueWith($state->withOutput('executed'));
                    }
                    return ActionPipelineDecision::continueWith($state);
                }
            },
            ActionPipelineStage::cases(),
        );
    }
}

final class FakeOutcomeAuditor implements \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor
{
    public int $calls = 0;

    public function record(\SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
    }
}
