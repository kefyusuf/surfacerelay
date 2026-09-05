<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Authorization\AuthorizationRuleNotConfigured;
use SurfaceRelay\Laravel\Authorization\DuplicateAuthorizationRule;
use SurfaceRelay\Laravel\Authorization\InMemoryActionAuthorizationRules;
use SurfaceRelay\Laravel\Authorization\LaravelAuthorizationRule;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class InMemoryActionAuthorizationRulesTest extends TestCase
{
    public function test_registers_and_retrieves_rule_for_exact_identity(): void
    {
        $provider = new InMemoryActionAuthorizationRules();
        $rule = new LaravelAuthorizationRule('refund');
        $provider->register($this->definition('orders.refund.prepare', 1), $rule);

        self::assertSame($rule, $provider->ruleFor($this->definition('orders.refund.prepare', 1)));
    }

    public function test_same_id_different_versions_are_independent(): void
    {
        $provider = new InMemoryActionAuthorizationRules();
        $ruleV1 = new LaravelAuthorizationRule('refund.draft');
        $ruleV2 = new LaravelAuthorizationRule('refund.commit');
        $provider->register($this->definition('foo.bar', 1), $ruleV1);
        $provider->register($this->definition('foo.bar', 2), $ruleV2);

        self::assertSame($ruleV1, $provider->ruleFor($this->definition('foo.bar', 1)));
        self::assertSame($ruleV2, $provider->ruleFor($this->definition('foo.bar', 2)));
    }

    public function test_duplicate_exact_registration_is_rejected(): void
    {
        $provider = new InMemoryActionAuthorizationRules();
        $provider->register($this->definition('foo.bar', 1), new LaravelAuthorizationRule('refund'));

        try {
            $provider->register($this->definition('foo.bar', 1), new LaravelAuthorizationRule('refund'));
            self::fail('Expected DuplicateAuthorizationRule.');
        } catch (DuplicateAuthorizationRule $exception) {
            self::assertSame('foo.bar', $exception->id);
            self::assertSame(1, $exception->version);
        }
    }

    public function test_duplicate_identity_with_different_definition_metadata_still_rejected(): void
    {
        $provider = new InMemoryActionAuthorizationRules();
        $provider->register($this->definition('foo.bar', 1, title: 'First'), new LaravelAuthorizationRule('a'));

        $this->expectException(DuplicateAuthorizationRule::class);
        $provider->register($this->definition('foo.bar', 1, title: 'Second'), new LaravelAuthorizationRule('a'));
    }

    public function test_missing_exact_rule_set_fails(): void
    {
        $provider = new InMemoryActionAuthorizationRules();

        try {
            $provider->ruleFor($this->definition('missing.action', 1));
            self::fail('Expected AuthorizationRuleNotConfigured.');
        } catch (AuthorizationRuleNotConfigured $exception) {
            self::assertSame('missing.action', $exception->id);
            self::assertSame(1, $exception->version);
        }
    }

    public function test_v2_rule_never_satisfies_v1(): void
    {
        $provider = new InMemoryActionAuthorizationRules();
        $provider->register($this->definition('foo.bar', 2), new LaravelAuthorizationRule('v2-ability'));

        $this->expectException(AuthorizationRuleNotConfigured::class);
        $this->expectExceptionMessage('version 1');
        $provider->ruleFor($this->definition('foo.bar', 1));
    }

    public function test_configured_argument_resolver_is_preserved(): void
    {
        $resolver = static fn (array $input, InvocationContext $context): array => [$input['amount'], $context];
        $rule = new LaravelAuthorizationRule('refund', $resolver);
        $provider = new InMemoryActionAuthorizationRules();
        $provider->register($this->definition('foo.bar', 1), $rule);

        $context = new InvocationContext('test', 'corr-1');
        $retrieved = $provider->ruleFor($this->definition('foo.bar', 1));

        self::assertSame([100, $context], $retrieved->resolveArguments(['amount' => 100], $context));
    }

    public function test_empty_ability_string_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty');
        new LaravelAuthorizationRule('');
    }

    public function test_whitespace_ability_is_not_trimmed(): void
    {
        $rule = new LaravelAuthorizationRule(' refund ');

        self::assertSame(' refund ', $rule->ability, 'No silent normalization of application configuration.');
    }

    // ------------------------------------------------------------------

    private function definition(string $id, int $version, string $title = 'Prepare refund'): ActionDefinition
    {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: $title,
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
}
