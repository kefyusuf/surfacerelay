<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\DuplicateTrustedContext;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextNotAvailable;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class InvocationContextTest extends TestCase
{
    // ------------------------------------------------------------------
    // Basic trusted lookup
    // ------------------------------------------------------------------

    public function test_trusted_lookup_has_get_require(): void
    {
        $context = $this->baseContext([
            $this->entry(ContextRequirement::AuthenticatedActor, 'real-user'),
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
        ]);

        self::assertTrue($context->has(ContextRequirement::AuthenticatedActor));
        self::assertTrue($context->has(ContextRequirement::Tenant));
        self::assertFalse($context->has(ContextRequirement::BrowserSession));

        $actor = $context->get(ContextRequirement::AuthenticatedActor);
        self::assertNotNull($actor);
        self::assertSame('real-user', $actor->value);

        self::assertSame('tenant-A', $context->require(ContextRequirement::Tenant)->value);
        self::assertNull($context->get(ContextRequirement::CurrentRecord));
    }

    public function test_provenance_is_preserved_verbatim(): void
    {
        $entry = new TrustedContextEntry(
            ContextRequirement::Tenant,
            'tenant-A',
            new ContextProvenance('tenant.resolver', 'db-connection-1'),
        );
        $context = $this->baseContext([$entry]);

        $resolved = $context->require(ContextRequirement::Tenant);
        self::assertSame('tenant.resolver', $resolved->provenance->provider);
        self::assertSame('db-connection-1', $resolved->provenance->reference);
    }

    public function test_require_throws_for_missing_requirement(): void
    {
        $context = $this->baseContext([]);

        $this->expectException(TrustedContextNotAvailable::class);
        $this->expectExceptionMessage('current_record');
        $context->require(ContextRequirement::CurrentRecord);
    }

    public function test_duplicate_trusted_entries_are_rejected(): void
    {
        $this->expectException(DuplicateTrustedContext::class);
        $this->expectExceptionMessage('tenant');
        $this->baseContext([
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
            $this->entry(ContextRequirement::Tenant, 'tenant-B'),
        ]);
    }

    public function test_duplicate_actor_entries_are_rejected(): void
    {
        $this->expectException(DuplicateTrustedContext::class);
        $this->baseContext([
            $this->entry(ContextRequirement::AuthenticatedActor, 'user-1'),
            $this->entry(ContextRequirement::AuthenticatedActor, 'user-2'),
        ]);
    }

    // ------------------------------------------------------------------
    // Deterministic ordering
    // ------------------------------------------------------------------

    public function test_all_trusted_returns_canonical_declaration_order(): void
    {
        $context = $this->baseContext([
            $this->entry(ContextRequirement::HumanConfirmation, 'opaque-receipt'),
            $this->entry(ContextRequirement::CurrentSelection, [10, 11]),
            $this->entry(ContextRequirement::AuthenticatedActor, 'real-user'),
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
            $this->entry(ContextRequirement::BrowserSession, 'session-1'),
            $this->entry(ContextRequirement::CurrentRecord, 42),
        ]);

        self::assertSame(
            [
                'authenticated_actor',
                'tenant',
                'current_record',
                'current_selection',
                'browser_session',
                'human_confirmation',
            ],
            array_map(
                static fn (TrustedContextEntry $entry): string => $entry->requirement->value,
                $context->allTrusted(),
            ),
        );
    }

    // ------------------------------------------------------------------
    // Presence vs value
    // ------------------------------------------------------------------

    public function test_empty_selection_is_present_and_not_missing(): void
    {
        $context = $this->baseContext([
            $this->entry(ContextRequirement::CurrentSelection, []),
        ]);

        self::assertTrue($context->has(ContextRequirement::CurrentSelection),
            'Presence must not be inferred from PHP truthiness.');
        self::assertSame([], $context->require(ContextRequirement::CurrentSelection)->value);
    }

    public function test_falsy_values_still_count_as_present(): void
    {
        $context = $this->baseContext([
            $this->entry(ContextRequirement::CurrentRecord, 0),
            $this->entry(ContextRequirement::BrowserSession, false),
        ]);

        self::assertTrue($context->has(ContextRequirement::CurrentRecord));
        self::assertTrue($context->has(ContextRequirement::BrowserSession));
        self::assertSame(0, $context->require(ContextRequirement::CurrentRecord)->value);
        self::assertSame(false, $context->require(ContextRequirement::BrowserSession)->value);
    }

    public function test_null_trusted_value_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not hold null');
        $this->baseContext([
            $this->entry(ContextRequirement::Tenant, null),
        ]);
    }

    // ------------------------------------------------------------------
    // Spoofing regressions (D-007 implemented in PHP)
    // ------------------------------------------------------------------

    public function test_caller_payload_keys_never_become_trusted_context(): void
    {
        // Deliberately uses keys identical to canonical context requirement values.
        $callerInput = [
            'authenticated_actor' => 'attacker',
            'tenant' => 'attacker-tenant',
            'current_record' => 999,
            'current_selection' => [999],
            'browser_session' => 'fake-session',
            'human_confirmation' => 'fake-receipt',
        ];

        // The context is built exclusively from trusted runtime entries; the
        // caller payload is passed nowhere near the trusted context argument.
        $context = $this->baseContext([
            $this->entry(ContextRequirement::AuthenticatedActor, 'real-user'),
            $this->entry(ContextRequirement::Tenant, 'tenant-A'),
            $this->entry(ContextRequirement::CurrentSelection, [10, 11]),
        ]);

        self::assertSame('real-user', $context->require(ContextRequirement::AuthenticatedActor)->value);
        self::assertSame('tenant-A', $context->require(ContextRequirement::Tenant)->value);
        self::assertSame([10, 11], $context->require(ContextRequirement::CurrentSelection)->value);

        self::assertFalse($context->has(ContextRequirement::CurrentRecord));
        self::assertFalse($context->has(ContextRequirement::BrowserSession));
        self::assertFalse($context->has(ContextRequirement::HumanConfirmation));

        // No API exists that would hydrate the payload into trusted context.
        $reflection = new \ReflectionClass(InvocationContext::class);
        $methods = implode('|', array_map(
            static fn (\ReflectionMethod $method): string => $method->getName(),
            $reflection->getMethods(\ReflectionMethod::IS_PUBLIC),
        ));
        self::assertStringNotContainsString('fromInput', $methods);
        self::assertStringNotContainsString('hydrate', $methods);
        self::assertSame(
            ['attacker-tenant', [999]],
            [$callerInput['tenant'], $callerInput['current_selection']],
            'Caller payload remains ordinary data.',
        );
    }

    public function test_metadata_is_never_a_fallback_authority_source(): void
    {
        $context = $this->baseContext([], metadata: [
            'tenant' => 'attacker',
            'human_confirmation' => 'yes',
            'current_selection' => [999],
        ]);

        self::assertFalse($context->has(ContextRequirement::Tenant));
        self::assertFalse($context->has(ContextRequirement::HumanConfirmation));
        self::assertFalse($context->has(ContextRequirement::CurrentSelection));

        $this->expectException(TrustedContextNotAvailable::class);
        $context->require(ContextRequirement::Tenant);
    }

    public function test_idempotency_key_grants_no_context_authority(): void
    {
        $context = $this->baseContext([], idempotencyKey: 'key-1');

        foreach (ContextRequirement::cases() as $requirement) {
            self::assertFalse(
                $context->has($requirement),
                sprintf('idempotencyKey must not satisfy requirement "%s".', $requirement->value),
            );
        }
    }

    public function test_surface_label_is_not_authorization(): void
    {
        $context = $this->baseContext([], surface: 'webmcp');

        foreach (ContextRequirement::cases() as $requirement) {
            self::assertFalse(
                $context->has($requirement),
                sprintf('surface must not satisfy requirement "%s".', $requirement->value),
            );
        }
    }

    // ------------------------------------------------------------------
    // Surface / correlation validation
    // ------------------------------------------------------------------

    public function test_empty_surface_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('surface');
        new InvocationContext('', 'corr-1');
    }

    public function test_empty_correlation_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('correlationId');
        new InvocationContext('webmcp', '');
    }

    public function test_non_entry_trusted_context_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TrustedContextEntry');
        $this->baseContext(['tenant' => 'attacker-tenant']);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function baseContext(
        array $trustedContext,
        string $surface = 'webmcp',
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InvocationContext {
        return new InvocationContext(
            surface: $surface,
            correlationId: 'corr-1',
            trustedContext: $trustedContext,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
        );
    }

    private function entry(ContextRequirement $requirement, mixed $value): TrustedContextEntry
    {
        return new TrustedContextEntry(
            $requirement,
            $value,
            new ContextProvenance('test.resolver'),
        );
    }
}
