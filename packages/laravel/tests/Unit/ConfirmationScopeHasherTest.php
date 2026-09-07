<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Auth\LaravelAuthenticatedActorResolver;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\UnrepresentableConfirmationScope;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ConfirmationScopeHasherTest extends TestCase
{
    public function test_associative_input_order_is_canonical(): void
    {
        $hasher = new ConfirmationScopeHasher();

        $a = $hasher->fingerprint($this->state(
            input: ['b' => 2, 'a' => ['z' => 3, 'y' => 2]],
        ));
        $b = $hasher->fingerprint($this->state(
            input: ['a' => ['y' => 2, 'z' => 3], 'b' => 2],
        ));

        self::assertSame($a, $b);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $a);
    }

    #[DataProvider('scopeMutationProvider')]
    public function test_each_authority_or_intent_dimension_changes_the_scope(string $dimension): void
    {
        $hasher = new ConfirmationScopeHasher();
        $base = $hasher->fingerprint($this->state());

        $mutated = match ($dimension) {
            'action_id' => $this->state(actionId: 'orders.refund.execute'),
            'action_version' => $this->state(actionVersion: 2),
            'surface' => $this->state(surface: 'headless'),
            'binding' => $this->state(bindingId: 'binding-2'),
            'input' => $this->state(input: ['amount' => 101]),
            'actor' => $this->state(actor: 'actor-2'),
            'tenant' => $this->state(tenant: 'tenant-2'),
            'record' => $this->state(record: 'record-2'),
            'selection' => $this->state(selection: ['order-2']),
            'session' => $this->state(session: 'session-2'),
            default => throw new \LogicException('Unknown scope mutation fixture.'),
        };

        self::assertNotSame($base, $hasher->fingerprint($mutated), $dimension);
    }

    /** @return array<string, array{string}> */
    public static function scopeMutationProvider(): array
    {
        return [
            'action id' => ['action_id'],
            'action version' => ['action_version'],
            'surface' => ['surface'],
            'binding' => ['binding'],
            'validated input' => ['input'],
            'actor' => ['actor'],
            'tenant' => ['tenant'],
            'current record' => ['record'],
            'current selection' => ['selection'],
            'browser session' => ['session'],
        ];
    }

    public function test_diagnostic_retry_metadata_and_human_confirmation_are_excluded(): void
    {
        $hasher = new ConfirmationScopeHasher();
        $base = $hasher->fingerprint($this->state());

        $context = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'different-correlation',
            trustedContext: [
                $this->entry(ContextRequirement::AuthenticatedActor, 'actor-1'),
                $this->entry(ContextRequirement::Tenant, 'tenant-1'),
                $this->entry(ContextRequirement::CurrentRecord, 'record-1'),
                $this->entry(ContextRequirement::CurrentSelection, ['order-1']),
                $this->entry(ContextRequirement::BrowserSession, 'session-1'),
                $this->entry(ContextRequirement::HumanConfirmation, 'preloaded-value'),
            ],
            idempotencyKey: 'different-idempotency',
            metadata: ['anything' => 'diagnostic-only'],
        );
        $changedDiagnostics = new ActionPipelineState(
            definition: $this->definition(),
            input: ['amount' => 100],
            context: $context,
            bindingId: 'binding-1',
        );

        self::assertSame($base, $hasher->fingerprint($changedDiagnostics));
    }

    public function test_explicit_scope_key_represents_object_without_serializing_it(): void
    {
        $hasher = new ConfirmationScopeHasher();
        $actorA = new ScopeObject('object-a');
        $actorB = new ScopeObject('object-b');

        $withA = $this->state(actor: $actorA, actorScopeKey: 'actor:model:42');
        $withB = $this->state(actor: $actorB, actorScopeKey: 'actor:model:42');

        self::assertSame($hasher->fingerprint($withA), $hasher->fingerprint($withB),
            'The trusted stable scope key, not object identity or serialization, binds authority.');
    }

    public function test_arbitrary_object_without_scope_key_fails_closed(): void
    {
        $this->expectException(UnrepresentableConfirmationScope::class);
        (new ConfirmationScopeHasher())->fingerprint($this->state(actor: new ScopeObject('unsafe')));
    }

    public function test_non_finite_float_and_mixed_key_map_fail_closed(): void
    {
        $hasher = new ConfirmationScopeHasher();

        try {
            $hasher->fingerprint($this->state(input: ['amount' => INF]));
            self::fail('INF must never be canonicalized.');
        } catch (UnrepresentableConfirmationScope) {
            self::assertTrue(true);
        }

        $this->expectException(UnrepresentableConfirmationScope::class);
        $hasher->fingerprint($this->state(input: ['ok' => 1, 4 => 'numeric-key']));
    }

    public function test_empty_confirmation_scope_key_is_invalid(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TrustedContextEntry(
            ContextRequirement::AuthenticatedActor,
            'actor-1',
            new ContextProvenance('test.auth'),
            confirmationScopeKey: '',
        );
    }

    public function test_composer_preserves_resolver_supplied_scope_keys(): void
    {
        $composer = new TrustedContextComposer(
            new ScopeActorResolver('actor-value', 'actor:key:1'),
            new ScopeTenantResolver('tenant-value', 'tenant:key:9'),
        );

        $entries = $composer->resolve();

        self::assertSame('actor:key:1', $entries[0]->confirmationScopeKey);
        self::assertSame('tenant:key:9', $entries[1]->confirmationScopeKey);
    }

    public function test_laravel_auth_resolver_derives_non_secret_stable_actor_scope_key(): void
    {
        $user = new ScopeAuthenticatableUser(42);
        $resolver = new LaravelAuthenticatedActorResolver(
            new ScopeAuthFactory(new ScopeAuthGuard($user)),
        );

        $resolved = $resolver->resolve();

        self::assertNotNull($resolved);
        self::assertSame($user, $resolved->value);
        self::assertSame(
            ScopeAuthenticatableUser::class . ':id:42',
            $resolved->confirmationScopeKey,
        );
        self::assertStringNotContainsString('password', $resolved->confirmationScopeKey);
        self::assertStringNotContainsString('remember', $resolved->confirmationScopeKey);
    }

    private function state(
        string $actionId = 'orders.refund.commit',
        int $actionVersion = 1,
        string $surface = 'webmcp',
        ?string $bindingId = 'binding-1',
        array $input = ['amount' => 100],
        mixed $actor = 'actor-1',
        ?string $actorScopeKey = null,
        mixed $tenant = 'tenant-1',
        mixed $record = 'record-1',
        mixed $selection = ['order-1'],
        mixed $session = 'session-1',
    ): ActionPipelineState {
        $entries = [
            $this->entry(ContextRequirement::AuthenticatedActor, $actor, $actorScopeKey),
            $this->entry(ContextRequirement::Tenant, $tenant),
            $this->entry(ContextRequirement::CurrentRecord, $record),
            $this->entry(ContextRequirement::CurrentSelection, $selection),
            $this->entry(ContextRequirement::BrowserSession, $session),
        ];

        return new ActionPipelineState(
            definition: $this->definition($actionId, $actionVersion),
            input: $input,
            context: new InvocationContext(
                surface: $surface,
                correlationId: 'corr-1',
                trustedContext: $entries,
                idempotencyKey: 'idem-1',
                metadata: ['locale' => 'tr-TR'],
            ),
            bindingId: $bindingId,
        );
    }

    private function entry(
        ContextRequirement $requirement,
        mixed $value,
        ?string $scopeKey = null,
    ): TrustedContextEntry {
        return new TrustedContextEntry(
            $requirement,
            $value,
            new ContextProvenance('test.' . $requirement->value),
            confirmationScopeKey: $scopeKey,
        );
    }

    private function definition(
        string $id = 'orders.refund.commit',
        int $version = 1,
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: 'Commit refund',
            description: 'Commits an approved refund.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentRecord,
                ContextRequirement::CurrentSelection,
                ContextRequirement::BrowserSession,
                ContextRequirement::HumanConfirmation,
            ],
        );
    }
}

final class ScopeObject
{
    public function __construct(public string $label) {}
}

final class ScopeActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private mixed $value, private ?string $scopeKey) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('scope.auth'),
            confirmationScopeKey: $this->scopeKey,
        );
    }
}

final class ScopeTenantResolver implements TenantResolver
{
    public function __construct(private mixed $value, private ?string $scopeKey) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('scope.tenant'),
            confirmationScopeKey: $this->scopeKey,
        );
    }
}

final class ScopeAuthenticatableUser implements Authenticatable
{
    public function __construct(private int $id) {}

    public function getAuthIdentifierName() { return 'id'; }
    public function getAuthIdentifier() { return $this->id; }
    public function getAuthPasswordName() { return 'password'; }
    public function getAuthPassword() { return 'never-include-this-password'; }
    public function getRememberToken() { return 'never-include-this-token'; }
    public function setRememberToken($value) {}
    public function getRememberTokenName() { return 'remember_token'; }
}

final class ScopeAuthFactory implements AuthFactory
{
    public function __construct(private Guard $guard) {}
    public function guard($name = null): Guard { return $this->guard; }
    public function shouldUse($name): void {}
}

final class ScopeAuthGuard implements Guard
{
    public function __construct(private ?object $user) {}
    public function check(): bool { return $this->user !== null; }
    public function guest(): bool { return $this->user === null; }
    public function user(): ?object { return $this->user; }
    public function id(): int|string|null { return null; }
    public function validate(array $credentials = []): bool { return false; }
    public function setUser($user): void {}
    public function hasUser(): bool { return $this->user !== null; }
}
