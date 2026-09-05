<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;
use SurfaceRelay\Laravel\Validation\DuplicateValidationRules;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\ValidationRulesNotConfigured;

final class InMemoryActionValidationRulesTest extends TestCase
{
    public function test_registers_and_retrieves_rules_for_exact_identity(): void
    {
        $provider = new InMemoryActionValidationRules();
        $definition = $this->definition('orders.refund.prepare', 1);
        $rules = ['reason' => ['required', 'string', 'min:3']];
        $provider->register($definition, $rules);

        self::assertSame($rules, $provider->rulesFor($definition));
    }

    public function test_same_id_different_versions_are_independent(): void
    {
        $provider = new InMemoryActionValidationRules();
        $v1 = $this->definition('foo.bar', 1);
        $v2 = $this->definition('foo.bar', 2);
        $provider->register($v1, ['reason' => 'required|string']);
        $provider->register($v2, ['reason' => ['required', 'string', 'max:10']]);

        self::assertSame(['reason' => 'required|string'], $provider->rulesFor($v1));
        self::assertSame(['reason' => ['required', 'string', 'max:10']], $provider->rulesFor($v2));
    }

    public function test_duplicate_exact_identity_is_rejected(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition('foo.bar', 1), ['reason' => 'required']);

        try {
            $provider->register($this->definition('foo.bar', 1), ['reason' => 'required|string']);
            self::fail('Expected DuplicateValidationRules.');
        } catch (DuplicateValidationRules $exception) {
            self::assertSame('foo.bar', $exception->id);
            self::assertSame(1, $exception->version);
        }
    }

    public function test_duplicate_identity_with_different_definition_metadata_still_rejected(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition('foo.bar', 1, title: 'First title'), []);

        try {
            $provider->register($this->definition('foo.bar', 1, title: 'Second title'), []);
            self::fail('Expected DuplicateValidationRules.');
        } catch (DuplicateValidationRules $exception) {
            self::assertSame('foo.bar', $exception->id);
        }
    }

    public function test_missing_exact_rule_set_fails(): void
    {
        $provider = new InMemoryActionValidationRules();
        $definition = $this->definition('missing.action', 1);

        try {
            $provider->rulesFor($definition);
            self::fail('Expected ValidationRulesNotConfigured.');
        } catch (ValidationRulesNotConfigured $exception) {
            self::assertSame('missing.action', $exception->id);
            self::assertSame(1, $exception->version);
        }
    }

    public function test_v2_rules_never_satisfy_v1(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition('foo.bar', 2), ['reason' => 'required']);

        $this->expectException(ValidationRulesNotConfigured::class);
        $this->expectExceptionMessage('version 1');
        $provider->rulesFor($this->definition('foo.bar', 1));
    }

    public function test_explicitly_registered_empty_rules_differ_from_missing(): void
    {
        $provider = new InMemoryActionValidationRules();
        $provider->register($this->definition('empty.rules', 1), []);

        self::assertSame([], $provider->rulesFor($this->definition('empty.rules', 1)),
            'Deliberate [] configuration is valid and retrievable.');

        $this->expectException(ValidationRulesNotConfigured::class);
        $provider->rulesFor($this->definition('unconfigured.action', 1));
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
            contextRequirements: [],
        );
    }
}
