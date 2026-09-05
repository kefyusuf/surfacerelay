<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Attributes\AgentAction;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;

final class AgentActionTest extends TestCase
{
    private function attribute(array $contextRequirements): AgentAction
    {
        return new AgentAction(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft.',
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            contextRequirements: $contextRequirements,
        );
    }

    public function test_valid_context_requirement_enums_are_preserved(): void
    {
        $attribute = $this->attribute([
            ContextRequirement::AuthenticatedActor,
            ContextRequirement::Tenant,
        ]);

        self::assertSame(
            [ContextRequirement::AuthenticatedActor, ContextRequirement::Tenant],
            $attribute->contextRequirements,
        );
        self::assertSame(OutputSensitivity::Normal, $attribute->outputSensitivity);
        self::assertSame(OutputContentTrust::TrustedApplicationData, $attribute->outputContentTrust);
    }

    public function test_arbitrary_strings_are_not_accepted_as_context_configuration(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('ContextRequirement');

        // @phpstan-ignore-next-line deliberately invalid configuration under test
        $this->attribute(['tenant']);
    }
}
