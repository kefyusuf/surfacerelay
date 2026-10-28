<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

/**
 * Quickstart step 1 (trusted context): this demo app is single-tenant, so the
 * tenant is a server-side constant — never caller input. Guests fail closed.
 * Multi-tenant apps derive this from their own membership model instead.
 */
final class FixedTenantResolver implements TenantResolver
{
    public const TENANT = 'quickstart-tenant';

    public function resolve(): ?ResolvedTrustedValue
    {
        if (Auth::guard('web')->user() === null) {
            return null;
        }

        return new ResolvedTrustedValue(
            value: self::TENANT,
            provenance: new ContextProvenance('quickstart.fixed-tenant'),
            confirmationScopeKey: self::TENANT,
        );
    }
}
