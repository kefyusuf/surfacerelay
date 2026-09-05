<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Contracts\Auth\Access\Gate;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Authorization\InMemoryActionAuthorizationRules;
use SurfaceRelay\Laravel\Authorization\LaravelAuthorizationRule;
use SurfaceRelay\Laravel\Authorization\LaravelGateActionAuthorizer;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;

final class LaravelGateActionAuthorizerTest extends TestCase
{
    private FakeAccessGate $gate;

    protected function setUp(): void
    {
        $this->gate = new FakeAccessGate();
    }

    // ------------------------------------------------------------------
    // LaravelGateActionAuthorizer
    // ------------------------------------------------------------------

    public function test_uses_trusted_actor_from_context_through_for_user(): void
    {
        $authorizer = $this->authorizer();
        $context = $this->contextWithActor('user-A');

        $allowed = $authorizer->allows($this->definition(), ['amount' => 100], $context);

        self::assertTrue($allowed);
        self::assertSame(['user-A'], $this->gate->forUserCalls, 'Authorization evaluates exactly the trusted actor.');
        self::assertSame('refund', $this->gate->lastScoped->allowsCalls[0]['ability']);
        self::assertSame([], $this->gate->lastScoped->allowsCalls[0]['arguments'], 'No automatic Gate argument injection.');
    }

    public function test_missing_actor_uses_for_user_null_without_ambient_fallback(): void
    {
        $authorizer = $this->authorizer();

        $allowed = $authorizer->allows($this->definition(), [], $this->context([]));

        self::assertTrue($allowed);
        self::assertSame([null], $this->gate->forUserCalls, 'Guest-capable abilities authorize forUser(null); ambient user is never consulted.');
    }

    public function test_unit_enum_ability_is_passed_unchanged(): void
    {
        $authorizer = $this->authorizer(new LaravelAuthorizationRule(FakeAbility::Refund));

        $authorizer->allows($this->definition(), [], $this->context([]));

        self::assertSame(FakeAbility::Refund, $this->gate->lastScoped->allowsCalls[0]['ability']);
    }

    public function test_argument_resolver_receives_validated_input_and_same_context(): void
    {
        $context = $this->context([
            $this->entry(ContextRequirement::CurrentRecord, $order = new \stdClass()),
        ]);
        $receivedContext = null;
        $rule = new LaravelAuthorizationRule('update', function (array $input, InvocationContext $ctx) use (&$receivedContext): array {
            $receivedContext = $ctx;

            return [$input['order']];
        });
        $authorizer = $this->authorizer($rule);

        $authorizer->allows($this->definition(), ['order' => $order], $context);

        self::assertSame($context, $receivedContext, 'The resolver receives the same trusted context instance.');
        self::assertSame(
            ['ability' => 'update', 'arguments' => [$order]],
            $this->gate->lastScoped->allowsCalls[0],
            'Policy-style arguments flow to the scoped Gate unchanged.',
        );
    }

    public function test_denial_returns_false(): void
    {
        $this->gate->result = false;
        $authorizer = $this->authorizer();

        self::assertFalse($authorizer->allows($this->definition(), [], $this->context([])));
    }

    public function test_actor_is_taken_only_from_trusted_context_not_metadata(): void
    {
        $authorizer = $this->authorizer();
        $context = new InvocationContext('test', 'corr-1', [], metadata: ['authenticated_actor' => 'attacker']);

        $authorizer->allows($this->definition(), ['authenticated_actor' => 'attacker'], $context);

        self::assertSame([null], $this->gate->forUserCalls, 'Metadata/payload actor spoofing never reaches Gate.');
    }

    // ------------------------------------------------------------------
    // AuthorizationStage
    // ------------------------------------------------------------------

    public function test_authorization_stage_halts_on_denial_without_mutating_state(): void
    {
        $this->gate->result = false;
        $stage = new AuthorizationStage($this->authorizer());
        $state = new ActionPipelineState($this->definition(), ['amount' => 100], $this->context([]));

        $decision = $stage->process($state);

        self::assertFalse($decision->continue);
        self::assertSame('authorization_denied', $decision->halt->code);
        self::assertNull($decision->halt->details);
        self::assertSame($state, $decision->state, 'Authorization is observational; state is not mutated.');
    }

    public function test_authorization_stage_continues_on_allow(): void
    {
        $stage = new AuthorizationStage($this->authorizer());
        $state = new ActionPipelineState($this->definition(), ['amount' => 100], $this->context([]));

        $decision = $stage->process($state);

        self::assertTrue($decision->continue);
        self::assertSame($state, $decision->state);
        self::assertSame(ActionPipelineStage::Authorization, $stage->stage());
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function authorizer(?LaravelAuthorizationRule $rule = null): LaravelGateActionAuthorizer
    {
        $provider = new InMemoryActionAuthorizationRules();
        $provider->register($this->definition(), $rule ?? new LaravelAuthorizationRule('refund'));

        return new LaravelGateActionAuthorizer($this->gate, $provider);
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
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::AuthenticatedActor],
        );
    }

    private function contextWithActor(string $actor): InvocationContext
    {
        return $this->context([
            $this->entry(ContextRequirement::AuthenticatedActor, $actor),
        ]);
    }

    private function context(array $entries): InvocationContext
    {
        return new InvocationContext('test', 'corr-1', $entries);
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry($requirement, $value, new ContextProvenance('test.resolver'));
    }
}

enum FakeAbility
{
    case Refund;
}

final class FakeAccessGate implements Gate
{
    /** @var list<mixed> */
    public array $forUserCalls = [];

    public ?FakeScopedGate $lastScoped = null;

    public bool $result = true;

    public function forUser($user)
    {
        $this->forUserCalls[] = $user;
        return $this->lastScoped = new FakeScopedGate($user, $this);
    }

    public function has($ability)
    {
        return true;
    }

    public function define($ability, $callback)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function resource($name, $class, ?array $abilities = null)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function policy($class, $policy)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function before(callable $callback)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function after(callable $callback)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function allows($ability, $arguments = []): bool
    {
        throw new \LogicException('Root/ambient Gate allows() must never be called.');
    }

    public function denies($ability, $arguments = []): bool
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function check($abilities, $arguments = []): bool
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function any($abilities, $arguments = []): bool
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function authorize($ability, $arguments = [])
    {
        throw new \LogicException('authorize() must not be used as control flow.');
    }

    public function inspect($ability, $arguments = [])
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function raw($ability, $arguments = [])
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function getPolicyFor($class)
    {
        throw new \LogicException('Not expected in fake.');
    }

    public function abilities(): array
    {
        return [];
    }
}

final class FakeScopedGate
{
    /** @var list<array{ability: \UnitEnum|string, arguments: array}> */
    public array $allowsCalls = [];

    public function __construct(
        public readonly mixed $user,
        private readonly FakeAccessGate $gate,
    ) {}

    public function allows(\UnitEnum|string $ability, array $arguments = []): bool
    {
        $this->allowsCalls[] = ['ability' => $ability, 'arguments' => $arguments];
        return $this->gate->result;
    }
}
