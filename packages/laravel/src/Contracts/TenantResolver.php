<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Contracts;

use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

/**
 * Resolves the application tenant from injected trusted application/runtime
 * services. Laravel has no canonical tenancy abstraction, so the consuming
 * application (or a future dedicated adapter) supplies the implementation.
 * The contract deliberately accepts NO arguments: route parameters,
 * subdomains, headers, query/body fields, and invocation metadata can never
 * select tenant authority from the generic kernel. Tenant membership
 * enforcement (does the actor belong to this tenant?) is a separate policy
 * concern, not this resolver's job.
 */
interface TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue;
}
