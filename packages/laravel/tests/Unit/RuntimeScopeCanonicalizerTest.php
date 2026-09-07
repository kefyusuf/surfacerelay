<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final class RuntimeScopeCanonicalizerTest extends TestCase
{
    public function test_runtime_scope_types_are_available(): void
    {
        $this->assertTypesAvailable();
    }

    public function test_maps_are_sorted_recursively_lists_keep_order_and_floats_keep_fraction(): void
    {
        $this->assertTypesAvailable();
        $canonicalizer = new RuntimeScopeCanonicalizer();

        self::assertSame(
            '{"a":{"y":2,"z":3},"b":2,"float":1.0,"list":[3,2,1]}',
            $canonicalizer->encode([
                'list' => [3, 2, 1],
                'float' => 1.0,
                'b' => 2,
                'a' => ['z' => 3, 'y' => 2],
            ], 'scope'),
        );
    }

    public function test_trusted_identity_prefers_stable_scope_key_over_object_value(): void
    {
        $this->assertTypesAvailable();
        $canonicalizer = new RuntimeScopeCanonicalizer();
        $entry = new TrustedContextEntry(
            ContextRequirement::AuthenticatedActor,
            new RuntimeScopeObject('must-not-be-inspected'),
            new ContextProvenance('test.auth'),
            confirmationScopeKey: 'actor:model:42',
        );

        self::assertSame(
            ['scopeKey' => 'actor:model:42'],
            $canonicalizer->trustedIdentity($entry, 'context.authenticated_actor'),
        );
    }

    public function test_unsupported_values_fail_closed_with_structural_path_only(): void
    {
        $this->assertTypesAvailable();
        $canonicalizer = new RuntimeScopeCanonicalizer();

        foreach ([
            'object' => new RuntimeScopeObject('secret-object-value'),
            'closure' => static fn (): string => 'secret-closure-value',
            'infinite_float' => INF,
            'mixed_key_map' => ['ok' => 1, 4 => 'secret-map-value'],
        ] as $name => $value) {
            try {
                $canonicalizer->encode(['payload' => $value], 'scope');
                self::fail($name . ' must not be canonicalized.');
            } catch (UnrepresentableRuntimeScope $e) {
                self::assertSame('scope.payload', $e->path);
                self::assertStringNotContainsString('secret', $e->getMessage());
            }
        }
    }

    private function assertTypesAvailable(): void
    {
        self::assertTrue(
            class_exists(RuntimeScopeCanonicalizer::class),
            'RuntimeScopeCanonicalizer must exist before canonicalization behavior can pass.',
        );
        self::assertTrue(
            class_exists(UnrepresentableRuntimeScope::class),
            'UnrepresentableRuntimeScope must exist before canonicalization behavior can pass.',
        );
    }
}

final class RuntimeScopeObject
{
    public function __construct(public string $label) {}
}
