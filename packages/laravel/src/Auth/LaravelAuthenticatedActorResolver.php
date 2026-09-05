<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Auth;

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
 * name as reference; no tokens, cookies, headers, or secrets are recorded.
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

        return new ResolvedTrustedValue(
            value: $user,
            provenance: new ContextProvenance(provider: 'laravel.auth', reference: $this->guard),
        );
    }
}
