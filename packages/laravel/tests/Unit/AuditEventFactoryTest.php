<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;

final class AuditEventFactoryTest extends TestCase
{
    public function test_semantic_audit_factory_type_exists_before_projection_behavior_is_added(): void
    {
        self::assertTrue(
            class_exists(AuditEventFactory::class),
            'AuditEventFactory must exist before structured audit projection can pass.',
        );
    }
}
