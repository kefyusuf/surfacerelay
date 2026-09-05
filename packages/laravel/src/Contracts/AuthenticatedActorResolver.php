<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Contracts;

use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

/**
 * Resolves the authenticated actor from injected trusted application/auth
 * services. The contract deliberately accepts NO arguments: caller input,
 * request payloads, and invocation metadata can never select or manufacture
 * actor authority. Absence (e.g. anonymous caller) is a null result, not an
 * exception; enforcement policy lives in ActionDefinition context
 * requirements, not in the resolver.
 */
interface AuthenticatedActorResolver
{
    public function resolve(): ?ResolvedTrustedValue;
}
