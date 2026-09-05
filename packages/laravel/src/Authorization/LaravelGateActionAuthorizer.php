<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Authorization;

use Illuminate\Contracts\Auth\Access\Gate;
use SurfaceRelay\Laravel\Contracts\ActionAuthorizer;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Single Laravel Gate adapter covering both Gate closures and Policies:
 * Laravel routes ability + arguments to policy methods automatically, so no
 * separate Policy adapter exists and no Gate::has() pre-check is performed
 * (a policy ability can be valid without a registered Gate closure).
 *
 * The actor is taken exclusively from ContextRequirement::AuthenticatedActor
 * in the trusted InvocationContext and applied through forUser() — Laravel's
 * ambient/current-user resolver is never consulted, so authorization
 * evaluates exactly the actor established by T-108 (null actor → forUser(null)
 * for intentionally guest-capable abilities). Denial is a boolean via
 * allows(); authorize()/AuthorizationException is never used as control flow
 * and no Gate Response messages leak into the pipeline (T-110 owns public
 * error semantics). Resolving the actor does not authorize; tenant membership
 * must be enforced by the configured policy, not by this adapter.
 */
final class LaravelGateActionAuthorizer implements ActionAuthorizer
{
    public function __construct(
        private readonly Gate $gate,
        private readonly ActionAuthorizationRulesProvider $rulesProvider,
    ) {}

    public function allows(ActionDefinition $definition, array $input, InvocationContext $context): bool
    {
        $rule = $this->rulesProvider->ruleFor($definition);

        $actor = $context->get(ContextRequirement::AuthenticatedActor)?->value;
        $scopedGate = $this->gate->forUser($actor);

        return $scopedGate->allows($rule->ability, $rule->resolveArguments($input, $context));
    }
}
