<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Contracts\ActionAuthorizer;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;

/**
 * Protocol-neutral Authorization stage: consults the ActionAuthorizer port
 * and halts with a stable internal reason on denial. Observational only —
 * it never mutates input, context, or output. Laravel Gate/Policy behavior
 * lives in the concrete ActionAuthorizer adapter (Authorization/ namespace).
 */
final readonly class AuthorizationStage implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly ActionAuthorizer $authorizer,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::Authorization;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        if (!$this->authorizer->allows($state->definition, $state->input, $state->context)) {
            return ActionPipelineDecision::halt(
                new ActionPipelineHalt(CoreActionErrorCode::AUTHORIZATION_DENIED),
                $state,
            );
        }

        return ActionPipelineDecision::continueWith($state);
    }
}
