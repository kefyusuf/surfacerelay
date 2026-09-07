<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

/**
 * Default Laravel adapter resolving the authenticated actor from Laravel
 * Guard/Auth state. One resolver instance has one deterministic auth source:
 * the guard is trusted application configuration supplied at construction —
 * never caller input — and there is no multi-guard fallback.
 *
 * Unauthenticated state resolves to null (absence), not to a placeholder
 * actor. The authenticated Laravel user object itself is the trusted value.
 * Provenance is diagnostic: provider `laravel.auth` with the configured guard
 * name as reference; no tokens, cookies, headers, passwords, or secrets are
 * recorded. When the user implements Laravel Authenticatable and exposes a
 * stable scalar/stringable identifier, a non-secret confirmation scope key is
 * derived from class + identifier-name + identifier.
 */
final class LaravelAuthenticatedActorResolver implements AuthenticatedActorResolver
{
    public function __construct(
        private readonly AuthFactory $auth,
        private readonly ?string $guard = null,
    ) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        $user = $this->auth->guard($this->guard)->user();
        if ($user === null) {
            return null;
        }

        $confirmationScopeKey = null;
        if ($user instanceof Authenticatable) {
            $identifierName = $user->getAuthIdentifierName();
            $identifier = $user->getAuthIdentifier();

            if (is_string($identifierName)
                && $identifierName !== ''
                && (is_scalar($identifier) || $identifier instanceof \Stringable)) {
                $confirmationScopeKey = implode(':', [
                    $user::class,
                    $identifierName,
                    (string) $identifier,
                ]);
            }
        }

        return new ResolvedTrustedValue(
            value: $user,
            provenance: new ContextProvenance(provider: 'laravel.auth', reference: $this->guard),
            confirmationScopeKey: $confirmationScopeKey,
        );
    }
}
