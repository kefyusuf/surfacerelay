<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Validation;

use Illuminate\Contracts\Validation\Factory;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

/**
 * Real implementation of ActionPipelineStage::InputValidation.
 *
 * Validates the caller input array with Laravel Validator using rules
 * supplied explicitly for the exact ActionDefinition identity. On success the
 * stage continues with the validator's validated dataset — unvalidated caller
 * fields do not proceed downstream, and there is no merge with the original
 * payload. On failure the stage halts with the stable INTERNAL reason
 * `input_validation_failed`; public ActionResult/error normalization is
 * T-110's concern.
 *
 * Validation operates exclusively on `$state->input`; the trusted
 * InvocationContext and its entries are never validated, hydrated, or
 * replaced here. Normal invalid input is an explicit halt — Laravel's
 * ValidationException is not used as control flow. Unexpected validator or
 * rule exceptions propagate (no catch-all).
 *
 * This stage is intentionally Laravel-specific (Illuminate Contracts only,
 * no facades); it never mutates ActionDefinition.inputSchema or the rules
 * provider, and performs no authorization (T-109).
 */
final class LaravelInputValidationStage implements ActionPipelineStageHandler
{
    public function __construct(
        private readonly Factory $validatorFactory,
        private readonly ActionValidationRulesProvider $rulesProvider,
    ) {}

    public function stage(): ActionPipelineStage
    {
        return ActionPipelineStage::InputValidation;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $rules = $this->rulesProvider->rulesFor($state->definition);

        $validator = $this->validatorFactory->make($state->input, $rules);
        if ($validator->fails()) {
            // Safe structural details only: unique failing field names in
            // deterministic lexical order. Never rejected values, Laravel
            // human-readable messages, rule parameters, or table names.
            $fields = array_values(array_unique($validator->errors()->keys()));
            sort($fields);

            return ActionPipelineDecision::halt(
                new ActionPipelineHalt(
                    CoreActionErrorCode::INPUT_VALIDATION_FAILED,
                    ['fields' => $fields],
                ),
                $state,
            );
        }

        return ActionPipelineDecision::continueWith($state->withInput($validator->validated()));
    }
}
