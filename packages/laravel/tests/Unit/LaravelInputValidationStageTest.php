<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
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
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\InvalidPipelineConfiguration;
use SurfaceRelay\Laravel\Runtime\Pipeline\PipelineInvariantViolation;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;
use SurfaceRelay\Laravel\Validation\ValidationRulesNotConfigured;

final class LaravelInputValidationStageTest extends TestCase
{
    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->validatorFactory = new ValidatorFactory(
            new Translator(new ArrayLoader(), 'en'),
        );
    }

    // ------------------------------------------------------------------
    // Stage unit behavior
    // ------------------------------------------------------------------

    public function test_valid_input_continues_with_validated_data(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), [
            'name' => ['required', 'string'],
            'age' => ['required', 'integer'],
        ]);

        $decision = $this->stage($provider)->process(
            $this->state(['name' => 'Alice', 'age' => 30]),
        );

        self::assertTrue($decision->continue);
        self::assertNull($decision->halt);
        self::assertSame(['name' => 'Alice', 'age' => 30], $decision->state->input);
    }

    public function test_invalid_input_halts_with_stable_internal_reason(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), ['name' => ['required', 'string']]);

        $decision = $this->stage($provider)->process(
            $this->state(['name' => '']),
        );

        self::assertFalse($decision->continue);
        self::assertSame('input_validation_failed', $decision->halt->code);
        self::assertSame(['fields' => ['name']], $decision->halt->details);
    }

    public function test_validated_dataset_replaces_raw_caller_input(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), ['name' => ['required', 'string']]);

        $decision = $this->stage($provider)->process(
            $this->state(['name' => 'Alice', 'admin' => true]),
        );

        self::assertTrue($decision->continue);
        self::assertSame(
            ['name' => 'Alice'],
            $decision->state->input,
            'Unvalidated caller fields must not proceed merely because they were in the payload.',
        );
    }

    public function test_pipe_string_and_array_rule_forms_are_both_accepted(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), [
            'name' => 'required|string|max:120',
            'age' => ['required', 'integer', 'min:0'],
        ]);

        $decision = $this->stage($provider)->process(
            $this->state(['name' => 'Alice', 'age' => 30]),
        );

        self::assertTrue($decision->continue);
        self::assertSame(['name' => 'Alice', 'age' => 30], $decision->state->input);
    }

    public function test_explicit_empty_rules_continue_but_forward_no_unvalidated_input(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), []);

        $decision = $this->stage($provider)->process(
            $this->state(['foo' => 'bar']),
        );

        // Documented SurfaceRelay behavior: Laravel's validated() dataset with
        // an empty rule set contains no fields, so the downstream input is []
        // — unvalidated caller input never proceeds. [] is valid
        // configuration; ValidationRulesNotConfigured must not be thrown.
        self::assertTrue($decision->continue);
        self::assertSame([], $decision->state->input);
    }

    public function test_rules_for_other_version_never_apply(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(version: 2), ['name' => ['required', 'string']]);

        $this->expectException(ValidationRulesNotConfigured::class);
        $this->expectExceptionMessage('version 1');
        $this->stage($provider)->process($this->state(['name' => 'Alice']));
    }

    public function test_trusted_context_is_untouched_by_validation(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition(), ['name' => ['required', 'string']]);

        $context = $this->context([
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
        ]);
        $state = new ActionPipelineState($this->definition(), ['name' => 'Alice', 'tenant' => 'attacker'], $context);

        $decision = $this->stage($provider)->process($state);

        self::assertTrue($decision->continue);
        self::assertSame($context, $decision->state->context, 'Validation must not replace the trusted context.');
        self::assertTrue($decision->state->context->has(ContextRequirement::Tenant));
        self::assertSame(
            'tenant-A',
            $decision->state->context->require(ContextRequirement::Tenant)->value,
            'A validated input field named "tenant" is business data, never ContextRequirement::Tenant authority.',
        );
    }

    // ------------------------------------------------------------------
    // ActionBus integration
    // ------------------------------------------------------------------

    public function test_bus_validation_failure_skips_every_later_stage_but_audits_once(): void
    {
        $log = [];
        $auditor = new ValidationRecordingAuditor($log);
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition();
        $registry->register($definition);

        $provider = new InMemoryActionValidationRules();
        $provider->register($definition, ['reason' => ['required', 'string', 'min:3']]);

        $bus = new ActionBus($registry, $auditor, $this->busHandlers($log, $this->stage($provider)));

        $outcome = $bus->dispatch($this->call($definition, ['reason' => 'no']));

        self::assertSame(['audit'], $log,
            'Fake handlers log only when executed; the real validation stage execution is evidenced by '
            . 'haltedAt. Authorization/confirmation/idempotency/execution/output-policy must not run after a validation halt.');
        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::InputValidation, $outcome->haltedAt);
        self::assertSame('input_validation_failed', $outcome->halt->code);
        self::assertSame(1, $auditor->calls);
    }

    public function test_bus_validation_success_passes_validated_input_to_later_stages(): void
    {
        $seenByAuthorization = null;
        $log = [];
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition();
        $registry->register($definition);

        $provider = new InMemoryActionValidationRules();
        $provider->register($definition, ['reason' => ['required', 'string']]);

        $stage = new LaravelInputValidationStage($this->validatorFactory, $provider);
        $handlers = $this->busHandlers($log, $stage, function (array $input) use (&$seenByAuthorization): void {
            $seenByAuthorization = $input;
        });

        $bus = new ActionBus($registry, new ValidationRecordingAuditor($log), $handlers);
        $outcome = $bus->dispatch($this->call($definition, ['reason' => 'damaged', 'extra_field' => 'dropped']));

        self::assertTrue($outcome->completed);
        self::assertSame(['reason' => 'damaged'], $seenByAuthorization,
            'Authorization must observe validated input, not the original payload.');
        self::assertSame(['reason' => 'damaged'], $outcome->state->input);
    }

    public function test_bus_validation_preserves_trusted_context_reference(): void
    {
        $log = [];
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition(requirements: [ContextRequirement::Tenant]);
        $registry->register($definition);

        $provider = new InMemoryActionValidationRules();
        $provider->register($definition, ['reason' => ['required', 'string']]);

        $context = $this->context([
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
        ]);
        $stage = new LaravelInputValidationStage($this->validatorFactory, $provider);
        $bus = new ActionBus($registry, new ValidationRecordingAuditor($log), $this->busHandlers($log, $stage));

        $outcome = $bus->dispatch(new ActionCall($definition->id, $definition->version, ['reason' => 'ok'], $context));

        self::assertTrue($outcome->completed);
        self::assertSame($context, $outcome->state->context,
            'The exact same context instance must flow through the pipeline unchanged.');
        self::assertSame('tenant-A', $outcome->state->context->require(ContextRequirement::Tenant)->value);
    }

    public function test_bus_with_real_stage_requires_later_stages_too(): void
    {
        $log = [];
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition();
        $registry->register($definition);
        $stage = new LaravelInputValidationStage($this->validatorFactory, new InMemoryActionValidationRules());

        $this->expectException(InvalidPipelineConfiguration::class);
        new ActionBus($registry, new ValidationRecordingAuditor(), [$stage]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function stage(InMemoryActionValidationRules $provider): LaravelInputValidationStage
    {
        return new LaravelInputValidationStage($this->validatorFactory, $provider);
    }

    private function state(array $input): ActionPipelineState
    {
        return new ActionPipelineState($this->definition(), $input, $this->context([]));
    }

    private function busHandlers(
        array &$log,
        LaravelInputValidationStage $validationStage,
        ?\Closure $authorizationObserver = null,
    ): array {
        return [
            $validationStage,
            new ValidationProbeHandler(ActionPipelineStage::Authorization, $log,
                function (ActionPipelineState $state) use ($authorizationObserver) {
                    if ($authorizationObserver !== null) {
                        $authorizationObserver($state->input);
                    }

                    return $state;
                }),
            new ValidationProbeHandler(ActionPipelineStage::Confirmation, $log),
            new ValidationProbeHandler(ActionPipelineStage::Idempotency, $log),
            new ValidationProbeHandler(ActionPipelineStage::Execution, $log,
                static fn (ActionPipelineState $state) => $state->withOutput('executed')),
            new ValidationProbeHandler(ActionPipelineStage::OutputPolicy, $log),
        ];
    }

    private function definition(
        int $version = 1,
        array $requirements = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: 'orders.refund.prepare',
            version: $version,
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

    private function call(ActionDefinition $definition, array $input): ActionCall
    {
        return new ActionCall($definition->id, $definition->version, $input, $this->context([]));
    }

    private function context(array $entries): InvocationContext
    {
        return new InvocationContext('webmcp', 'corr-1', $entries);
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry($requirement, $value, new ContextProvenance('test.resolver'));
    }
}

/**
 * Test fake for the five non-validation stages: logs stage execution and can
 * observe the current input (authorization) or set output (execution).
 */
final class ValidationProbeHandler implements ActionPipelineStageHandler
{
    private readonly ActionPipelineStage $stage;

    /** @var list<string> */
    private array $log;

    private readonly ?\Closure $behavior;

    /** @param list<string> $log */
    public function __construct(ActionPipelineStage $stage, array &$log, ?\Closure $behavior = null)
    {
        $this->stage = $stage;
        $this->log = &$log;
        $this->behavior = $behavior;
    }

    public function stage(): ActionPipelineStage
    {
        return $this->stage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->log[] = $this->stage->value;
        if ($this->behavior !== null) {
            $result = ($this->behavior)($state);
            if ($result instanceof ActionPipelineDecision) {
                return $result;
            }
            return ActionPipelineDecision::continueWith($result);
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class ValidationRecordingAuditor implements \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor
{
    public int $calls = 0;

    /** @var list<string> */
    private array $log;

    /** @param list<string> $log */
    public function __construct(array &$log = [])
    {
        $this->log = &$log;
    }

    public function record(\SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall $call, \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
        $this->log[] = 'audit';
    }
}
