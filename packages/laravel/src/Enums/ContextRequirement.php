<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Enums;

/**
 * Trusted facts the runtime must resolve before invocation. Values mirror the
 * frozen spec/0.1 Action Definition `contextRequirements` vocabulary exactly;
 * they are resolved exclusively from trusted runtime context, never caller input.
 */
enum ContextRequirement: string
{
    case AuthenticatedActor = 'authenticated_actor';
    case Tenant = 'tenant';
    case CurrentRecord = 'current_record';
    case CurrentSelection = 'current_selection';
    case BrowserSession = 'browser_session';
    case HumanConfirmation = 'human_confirmation';
}
