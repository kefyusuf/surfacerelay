<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Enums\ContextRequirement;

/**
 * Composes trusted resolver results into canonical TrustedContextEntry
 * objects for InvocationContext. Resolvers are always consulted from
 * injected trusted services — never from caller input or invocation
 * metadata — and a null resolver result simply omits the entry (absence is
 * not exceptional; the ActionBus context-requirement gate enforces policy).
 *
 * Resolver-supplied stable confirmation scope keys are forwarded verbatim;
 * they help deterministic confirmation scoping but never grant authority by
 * themselves.
 *
 * Output is deterministic: canonical ContextRequirement declaration order
 * (authenticated_actor, tenant). No duplicate/merge handling beyond what
 * InvocationContext enforces, because this composer owns exactly one
 * resolver per requirement.
 */
final readonly class TrustedContextComposer
{
    public function __construct(
        private readonly AuthenticatedActorResolver $actorResolver,
        private readonly TenantResolver $tenantResolver,
    ) {}

    /** @return list<TrustedContextEntry> */
    public function resolve(): array
    {
        $entries = [];

        $actor = $this->actorResolver->resolve();
        if ($actor !== null) {
            $entries[] = new TrustedContextEntry(
                ContextRequirement::AuthenticatedActor,
                $actor->value,
                $actor->provenance,
                confirmationScopeKey: $actor->confirmationScopeKey,
            );
        }

        $tenant = $this->tenantResolver->resolve();
        if ($tenant !== null) {
            $entries[] = new TrustedContextEntry(
                ContextRequirement::Tenant,
                $tenant->value,
                $tenant->provenance,
                confirmationScopeKey: $tenant->confirmationScopeKey,
            );
        }

        return $entries;
    }
}
