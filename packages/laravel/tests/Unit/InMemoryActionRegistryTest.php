<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\ActionRegistry as ActionRegistryContract;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;
use SurfaceRelay\Laravel\Registry\ActionDefinitionNotFound;
use SurfaceRelay\Laravel\Registry\DuplicateActionDefinition;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

final class InMemoryActionRegistryTest extends TestCase
{
    // ------------------------------------------------------------------
    // Registration and exact lookup
    // ------------------------------------------------------------------

    public function test_registered_definition_is_findable_by_exact_identity(): void
    {
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition('orders.refund.prepare', 1);

        $registry->register($definition);

        self::assertInstanceOf(ActionRegistryContract::class, $registry);
        self::assertTrue($registry->has('orders.refund.prepare', 1));
        self::assertSame($definition, $registry->get('orders.refund.prepare', 1));
    }

    public function test_same_id_with_different_versions_coexist(): void
    {
        $registry = new InMemoryActionRegistry();
        $v1 = $this->definition('example.action', 1);
        $v2 = $this->definition('example.action', 2);
        $registry->register($v1);
        $registry->register($v2);

        self::assertTrue($registry->has('example.action', 1));
        self::assertTrue($registry->has('example.action', 2));
        self::assertSame($v1, $registry->get('example.action', 1));
        self::assertSame($v2, $registry->get('example.action', 2));
        self::assertCount(2, $registry->all());
    }

    // ------------------------------------------------------------------
    // Duplicate exact identity
    // ------------------------------------------------------------------

    public function test_duplicate_exact_identity_rejected_even_for_identical_metadata(): void
    {
        $registry = new InMemoryActionRegistry();
        $definition = $this->definition('orders.refund.prepare', 1);
        $registry->register($definition);

        $identicalCopy = $this->definition('orders.refund.prepare', 1);
        self::assertNotSame($definition, $identicalCopy);
        self::assertEquals($definition, $identicalCopy, 'Objects carry identical metadata…');

        try {
            $registry->register($identicalCopy);
            self::fail('Expected DuplicateActionDefinition to be thrown.');
        } catch (DuplicateActionDefinition $exception) {
            self::assertSame('orders.refund.prepare', $exception->id);
            self::assertSame(1, $exception->version);
        }

        self::assertCount(1, $registry->all(), 'Duplicate registration must not overwrite or merge.');
    }

    public function test_duplicate_identity_with_different_metadata_still_rejected(): void
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition('orders.refund.prepare', 1, title: 'Prepare refund'));

        try {
            $registry->register(
                $this->definition('orders.refund.prepare', 1, title: 'A different title')
            );
            self::fail('Expected DuplicateActionDefinition to be thrown.');
        } catch (DuplicateActionDefinition $exception) {
            self::assertSame('orders.refund.prepare', $exception->id);
            self::assertSame(1, $exception->version);
        }

        self::assertSame(
            'Prepare refund',
            $registry->get('orders.refund.prepare', 1)->title,
            'Identity determines uniqueness, not object equality; original stays intact.',
        );
    }

    // ------------------------------------------------------------------
    // Missing identity / no fallback
    // ------------------------------------------------------------------

    public function test_missing_exact_identity_throws(): void
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition('orders.refund.prepare', 1));

        self::assertFalse($registry->has('orders.refund.cancel', 1));

        try {
            $registry->get('orders.refund.cancel', 1);
            self::fail('Expected ActionDefinitionNotFound to be thrown.');
        } catch (ActionDefinitionNotFound $exception) {
            self::assertSame('orders.refund.cancel', $exception->id);
            self::assertSame(1, $exception->version);
        }
    }

    public function test_no_silent_fallback_to_another_version(): void
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition('example.action', 2));

        self::assertFalse($registry->has('example.action', 1), 'Requesting v1 must not match v2.');

        $this->expectException(ActionDefinitionNotFound::class);
        $this->expectExceptionMessage('version 1');
        $registry->get('example.action', 1);
    }

    // ------------------------------------------------------------------
    // Deterministic enumeration
    // ------------------------------------------------------------------

    public function test_all_returns_definitions_ordered_by_id_then_version(): void
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition('zeta.action', 2));
        $registry->register($this->definition('alpha.action', 2));
        $registry->register($this->definition('zeta.action', 1));
        $registry->register($this->definition('alpha.action', 1));

        self::assertSame(
            [
                ['alpha.action', 1],
                ['alpha.action', 2],
                ['zeta.action', 1],
                ['zeta.action', 2],
            ],
            array_map(
                static fn (ActionDefinition $definition): array => [$definition->id, $definition->version],
                $registry->all(),
            ),
            'all() ordering must be deterministic (id ASC, version ASC), independent of registration order.',
        );
    }

    public function test_empty_registry(): void
    {
        $registry = new InMemoryActionRegistry();

        self::assertSame([], $registry->all());
        self::assertFalse($registry->has('any.action', 1));
    }

    public function test_mutating_returned_list_does_not_corrupt_registry(): void
    {
        $registry = new InMemoryActionRegistry();
        $registry->register($this->definition('beta.action', 1));
        $registry->register($this->definition('alpha.action', 2));
        $registry->register($this->definition('alpha.action', 1));

        $returned = $registry->all();
        usort($returned, static fn (ActionDefinition $a, ActionDefinition $b): int => $b->id <=> $a->id);
        $returned[] = $this->definition('gamma.action', 1);
        sort($returned);

        self::assertSame(
            [
                ['alpha.action', 1],
                ['alpha.action', 2],
                ['beta.action', 1],
            ],
            array_map(
                static fn (ActionDefinition $definition): array => [$definition->id, $definition->version],
                $registry->all(),
            ),
        );
        self::assertFalse($registry->has('gamma.action', 1));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function definition(
        string $id,
        int $version,
        string $title = 'Prepare refund',
    ): ActionDefinition {
        return new ActionDefinition(
            id: $id,
            version: $version,
            title: $title,
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object', 'required' => ['reason']],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputTrust: OutputTrust::Sensitive,
            contextRequirements: [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentRecord,
            ],
        );
    }
}
