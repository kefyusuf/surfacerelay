<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Attributes;

use Attribute;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AgentAction
{
    /** @param list<ContextRequirement> $contextRequirements */
    public function __construct(
        public string $id,
        public int $version,
        public string $title,
        public string $description,
        public ActionScope $scope,
        public ActionEffect $effect,
        public ActionRisk $risk,
        public IdempotencyPolicy $idempotency = IdempotencyPolicy::None,
        public OutputSensitivity $outputSensitivity = OutputSensitivity::Normal,
        public OutputContentTrust $outputContentTrust = OutputContentTrust::TrustedApplicationData,
        public array $contextRequirements = [],
    ) {
        foreach ($this->contextRequirements as $requirement) {
            if (!$requirement instanceof ContextRequirement) {
                throw new \InvalidArgumentException(
                    'contextRequirements must only contain ContextRequirement enum values.'
                );
            }
        }
    }
}
