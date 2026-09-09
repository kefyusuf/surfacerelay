<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;

final readonly class StructuredActionPipelineAuditor implements ActionPipelineAuditor
{
    public function __construct(
        private AuditEventFactory $factory,
        private AuditEventStore $store,
    ) {}

    public function record(ActionCall $call, ActionPipelineOutcome $outcome): void
    {
        $this->store->append($this->factory->create($outcome));
    }
}
