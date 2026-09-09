<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class StructuredAuditIdempotencyReplayIntegrationTest extends TestCase
{
    public function test_replay_harness_exists_before_one_execution_two_final_events_can_be_proven(): void
    {
        self::assertTrue(
            class_exists(StructuredAuditReplayHarness::class),
            'StructuredAuditReplayHarness must exist before replay/audit invariants can pass.',
        );
    }
}
