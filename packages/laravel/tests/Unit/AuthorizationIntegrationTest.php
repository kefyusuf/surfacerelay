<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory as ValidatorFactory;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Authorization\AuthorizationRuleNotConfigured;
use SurfaceRelay\Laravel\Authorization\InMemoryActionAuthorizationRules;
use SurfaceRelay\Laravel\Authorization\LaravelAuthorizationRule;
use SurfaceRelay\Laravel\Authorization\LaravelGateActionAuthorizer;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\InvalidPipelineConfiguration;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final class AuthorizationIntegrationTest extends TestCase
{
    private FakeAccessGate $gate;

    private ValidatorFactory $validatorFactory;

    protected function setUp(): void
    {
        $this->gate = new FakeAccessGate();
        $this->validatorFactory = new ValidatorFactory(
            new Translator(new ArrayLoader(), 'en'),
        );
    }

    // ------------------------------------------------------------------
    // §31 — denial short-circuits later stages, audits once
    // ------------------------------------------------------------------

    public function test_authorization_denial_halts_before_confirmation_execution_and_audits_once(): void
    {
        $this->gate->result = false;
        $log = [];
        $auditor = new ValidationRecordingAuditor($log);
        $definition = $this->definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $authRules = new InMemoryActionAuthorizationRules();
        $authRules->register($definition, new LaravelAuthorizationRule('refund'));

        $bus = new ActionBus($registry, $auditor, array_merge(
            [new ValidationProbeHandler(ActionPipelineStage::InputValidation, $log)],
            [new AuthorizationStage(new LaravelGateActionAuthorizer($this->gate, $authRules))],
            $this->remainingFakeHandlers($log),
        ));

        $outcome = $bus->dispatch($this->call($definition, ['amount' => 100], $this->contextWithActor('real-user')));

        // The fake input_validation handler logs; the real authorization stage
        // does not — its execution is evidenced by haltedAt. The fake later
        // stages MUST NOT appear in the log.
        self::assertSame(['input_validation', 'audit'], $log);
        self::assertFalse($outcome->completed);
        self::assertSame(ActionPipelineStage::Authorization, $outcome->haltedAt);
        self::assertSame('authorization_denied', $outcome->halt->code);
        self::assertSame(1, $auditor->calls);
    }

    // ------------------------------------------------------------------
    // §32 — sanitized (validated) input is all authorization can see
    // ------------------------------------------------------------------

    public function test_authorization_arguments_receive_validated_input_not_raw_payload(): void
    {
        $seenInput = null;
        $seenArguments = null;
        $log = [];
        $this->gate->result = true;

        $definition = $this->definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $validationRules = new InMemoryActionValidationRules();
        $validationRules->register($definition, ['amount' => ['required', 'integer']]);

        $authRules = new InMemoryActionAuthorizationRules();
        $authRules->register($definition, new LaravelAuthorizationRule('refund',
            function (array $input, InvocationContext $context) use (&$seenInput, &$seenArguments): array {
                $seenInput = $input;
                $seenArguments = [$input['amount']];

                return $seenArguments;
            }));

        $validationStage = new LaravelInputValidationStage($this->validatorFactory, $validationRules);
        $authorizationStage = new AuthorizationStage(
            new LaravelGateActionAuthorizer($this->gate, $authRules),
        );

        $bus = new ActionBus($registry, new ValidationRecordingAuditor($log), array_merge(
            [$validationStage, $authorizationStage],
            $this->remainingFakeHandlers($log),
        ));

        // 'is_admin' is caller-controlled and must be dropped by validation
        // before authorization ever sees it.
        $outcome = $bus->dispatch(
            $this->call($definition, ['amount' => 100, 'is_admin' => true], $this->contextWithActor('real-user')),
        );

        self::assertTrue($outcome->completed);
        self::assertSame(['amount' => 100], $seenInput,
            'Raw caller fields must not bypass validation and influence authorization.');
        self::assertSame(
            ['ability' => 'refund', 'arguments' => [100]],
            $this->gate->lastScoped->allowsCalls[0],
        );
    }

    // ------------------------------------------------------------------
    // §33 — trusted actor spoofing closes the T-105/T-108/T-109 chain
    // ------------------------------------------------------------------

    public function test_gate_evaluates_trusted_actor_never_caller_supplied_actor(): void
    {
        $log = [];
        $this->gate->result = true;
        $definition = $this->definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $validationRules = new InMemoryActionValidationRules();
        $validationRules->register($definition, ['amount' => ['required', 'integer']]);

        $authRules = new InMemoryActionAuthorizationRules();
        $authRules->register($definition, new LaravelAuthorizationRule('refund'));

        $validationStage = new LaravelInputValidationStage($this->validatorFactory, $validationRules);
        $authorizationStage = new AuthorizationStage(
            new LaravelGateActionAuthorizer($this->gate, $authRules),
        );

        $bus = new ActionBus($registry, new ValidationRecordingAuditor($log), array_merge(
            [$validationStage, $authorizationStage],
            $this->remainingFakeHandlers($log),
        ));

        $context = new InvocationContext(
            'test',
            'corr-1',
            [new TrustedContextEntry(
                ContextRequirement::AuthenticatedActor,
                'real-user',
                new ContextProvenance('laravel.auth', 'web'),
            )],
            metadata: ['authenticated_actor' => 'attacker'],
        );

        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['amount' => 100, 'authenticated_actor' => 'attacker'],
            $context,
        ));

        self::assertTrue($outcome->completed);
        self::assertSame(['real-user'], $this->gate->forUserCalls,
            'Gate must evaluate the trusted actor from context, never attacker data from input/metadata.');
    }

    // ------------------------------------------------------------------
    // §25 — missing authorization configuration is a loud failure
    // ------------------------------------------------------------------

    public function test_missing_authorization_rule_is_a_configuration_failure_not_a_deny(): void
    {
        $log = [];
        $definition = $this->definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        // Validation rules configured; authorization rules deliberately NOT.
        $validationRules = new InMemoryActionValidationRules();
        $validationRules->register($definition, ['amount' => ['required', 'integer']]);

        $validationStage = new LaravelInputValidationStage($this->validatorFactory, $validationRules);
        $authorizationStage = new AuthorizationStage(
            new LaravelGateActionAuthorizer($this->gate, new InMemoryActionAuthorizationRules()),
        );

        $bus = new ActionBus($registry, $auditor = new ValidationRecordingAuditor($log), array_merge(
            [$validationStage, $authorizationStage],
            $this->remainingFakeHandlers($log),
        ));

        try {
            $bus->dispatch($this->call($definition, ['amount' => 100], $this->contextWithActor('real-user')));
            self::fail('Expected AuthorizationRuleNotConfigured.');
        } catch (AuthorizationRuleNotConfigured $exception) {
            self::assertSame('orders.refund.prepare', $exception->id);
            self::assertSame(1, $exception->version);
        }

        self::assertSame(0, $auditor->calls, 'Configuration failures are exceptional, not normal denials.');
    }

    public function test_pipeline_still_requires_all_handlers_with_real_authorization_stage(): void
    {
        $log = [];
        $definition = $this->definition();
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $authorizationStage = new AuthorizationStage(
            new LaravelGateActionAuthorizer($this->gate, new InMemoryActionAuthorizationRules()),
        );

        $this->expectException(InvalidPipelineConfiguration::class);
        new ActionBus($registry, new ValidationRecordingAuditor($log), [$authorizationStage]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return list<ValidationProbeHandler>
     */
    private function remainingFakeHandlers(array &$log): array
    {
        return [
            new ValidationProbeHandler(ActionPipelineStage::Confirmation, $log),
            new ValidationProbeHandler(ActionPipelineStage::Idempotency, $log),
            new ValidationProbeHandler(ActionPipelineStage::Execution, $log,
                static fn ($state) => $state->withOutput('executed')),
            new ValidationProbeHandler(ActionPipelineStage::OutputPolicy, $log),
        ];
    }

    private function definition(): ActionDefinition
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
            outputTrust: OutputTrust::Sensitive,
            contextRequirements: [ContextRequirement::AuthenticatedActor],
        );
    }

    private function call(ActionDefinition $definition, array $input, InvocationContext $context): ActionCall
    {
        return new ActionCall($definition->id, $definition->version, $input, $context);
    }

    private function contextWithActor(string $actor): InvocationContext
    {
        return new InvocationContext('test', 'corr-1', [
            new TrustedContextEntry(
                ContextRequirement::AuthenticatedActor,
                $actor,
                new ContextProvenance('laravel.auth', 'web'),
            ),
        ]);
    }
}
