<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Attributes;

use Attribute;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AgentAction
{
    /** @param list<string> $contextRequirements */
    public function __construct(
        public string $id,
        public int $version,
        public string $title,
        public string $description,
        public ActionScope $scope,
        public ActionEffect $effect,
        public ActionRisk $risk,
        public IdempotencyPolicy $idempotency = IdempotencyPolicy::None,
        public OutputTrust $outputTrust = OutputTrust::TrustedApplicationData,
        public array $contextRequirements = [],
    ) {}
}
