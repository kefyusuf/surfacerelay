<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Auth\LaravelAuthenticatedActorResolver;

final class LaravelAuthenticatedActorResolverTest extends TestCase
{
    public function test_resolves_authenticated_user_from_configured_guard(): void
    {
        $user = new ActorStub();
        $factory = new FakeAuthFactory(['web' => new FakeGuard($user)]);
        $resolver = new LaravelAuthenticatedActorResolver($factory);

        $resolved = $resolver->resolve();

        self::assertNotNull($resolved);
        self::assertSame($user, $resolved->value, 'The authenticated Laravel user object is the trusted value.');
        self::assertSame('laravel.auth', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
    }

    public function test_anonymous_guard_resolves_to_null(): void
    {
        $factory = new FakeAuthFactory(['web' => new FakeGuard(null)]);
        $resolver = new LaravelAuthenticatedActorResolver($factory);

        self::assertNull($resolver->resolve(), 'Unauthenticated state is absence, never a placeholder actor.');
    }

    public function test_explicit_guard_is_trusted_configuration_and_the_only_source(): void
    {
        $user = new ActorStub();
        $adminGuard = new FakeGuard($user);
        $webGuard = new FakeGuard(null);
        $factory = new FakeAuthFactory(['admin' => $adminGuard, 'web' => $webGuard]);

        $resolver = new LaravelAuthenticatedActorResolver($factory, guard: 'admin');
        $resolved = $resolver->resolve();

        self::assertSame($user, $resolved->value);
        self::assertSame('admin', $resolved->provenance->reference);
        self::assertSame(['admin'], $factory->requestedGuards,
            'Only the configured guard is consulted; no multi-guard fallback.');
    }

    public function test_resolver_api_accepts_no_caller_authority_arguments(): void
    {
        $reflection = new \ReflectionClass(LaravelAuthenticatedActorResolver::class);
        $resolve = $reflection->getMethod('resolve');

        self::assertSame(
            [],
            $resolve->getParameters(),
            'resolve() must accept no arguments: caller input can never select actor authority.',
        );

        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);
        foreach ($constructor->getParameters() as $parameter) {
            self::assertNotSame('userId', $parameter->getName());
        }
    }
}

final class ActorStub
{
}

/**
 * @param array<string, Guard> $guards
 */
final class FakeAuthFactory implements AuthFactory
{
    /** @var list<string|null> */
    public array $requestedGuards = [];

    public function __construct(
        private readonly array $guards,
    ) {}

    public function guard($name = null): Guard
    {
        $this->requestedGuards[] = $name;
        return $this->guards[$name ?? 'web']
            ?? throw new \InvalidArgumentException("Unknown guard \"$name\" in fake.");
    }

    public function shouldUse($name): void
    {
    }
}

final class FakeGuard implements Guard
{
    public function __construct(
        private readonly ?object $user,
    ) {}

    public function check(): bool
    {
        return $this->user !== null;
    }

    public function guest(): bool
    {
        return $this->user === null;
    }

    public function user(): ?object
    {
        return $this->user;
    }

    public function id(): int|string|null
    {
        return null;
    }

    public function validate(array $credentials = []): bool
    {
        return false;
    }

    public function setUser($user): void
    {
    }

    public function hasUser(): bool
    {
        return $this->user !== null;
    }
}
