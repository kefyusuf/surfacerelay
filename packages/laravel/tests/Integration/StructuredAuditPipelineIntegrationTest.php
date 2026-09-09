<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class StructuredAuditPipelineIntegrationTest extends TestCase
{
    public function test_structured_audit_pipeline_harness_exists_before_boundary_scenarios_are_wired(): void
    {
        self::assertTrue(
            class_exists(StructuredAuditIntegrationHarness::class),
            'StructuredAuditIntegrationHarness must exist before the real pipeline boundary scenarios can pass.',
        );
    }
}
