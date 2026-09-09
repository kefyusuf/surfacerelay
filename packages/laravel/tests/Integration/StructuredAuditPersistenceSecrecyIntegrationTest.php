<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class StructuredAuditPersistenceSecrecyIntegrationTest extends TestCase
{
    public function test_durable_secrecy_harness_exists_before_forbidden_marker_proof_can_pass(): void
    {
        self::assertTrue(
            class_exists(StructuredAuditPersistenceSecrecyHarness::class),
            'StructuredAuditPersistenceSecrecyHarness must exist before durable secrecy invariants can pass.',
        );
    }
}
