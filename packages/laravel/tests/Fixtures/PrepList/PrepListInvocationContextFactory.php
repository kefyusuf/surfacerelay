<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\PrepList;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class PrepListInvocationContextFactory
{
    private int $nextCorrelation = 1;

    public function create(): InvocationContext
    {
        return new InvocationContext(
            surface: 'livewire',
            correlationId: 'prep-list-' . $this->nextCorrelation++,
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::BrowserSession,
                    'prep-list-browser-session',
                    new ContextProvenance('prep-list.test', 'browser-session'),
                ),
            ],
        );
    }
}
