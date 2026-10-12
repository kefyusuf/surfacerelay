<?php

declare(strict_types=1);

namespace App\A2;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

/** Fixture-only resolver: an actor with zero or multiple eligible tenants fails closed. */
final class SingleMembershipTenantResolver implements TenantResolver
{
    public function resolve(): ?ResolvedTrustedValue
    {
        $actor = Auth::guard('web')->user();
        if ($actor === null) {
            return null;
        }

        $memberships = DB::table('a2_memberships')
            ->where('user_id', $actor->getAuthIdentifier())
            ->where('can_inspect', true)
            ->pluck('tenant_id')
            ->all();

        if (count($memberships) !== 1 || !is_string($memberships[0])) {
            return null;
        }

        $tenant = $memberships[0];

        return new ResolvedTrustedValue(
            value: $tenant,
            provenance: new ContextProvenance('a2.database.membership'),
            confirmationScopeKey: $tenant,
        );
    }
}
