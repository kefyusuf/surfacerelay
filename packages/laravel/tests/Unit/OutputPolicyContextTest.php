<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyContext;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class OutputPolicyContextTest extends TestCase
{
    #[Test]
    public function it_exposes_only_trusted_runtime_entries_in_canonical_order(): void
    {
        $actor = new TrustedContextEntry(
            ContextRequirement::AuthenticatedActor,
            (object) ['id' => 42],
            new ContextProvenance('test.actor'),
            'actor:42',
        );
        $tenant = new TrustedContextEntry(
            ContextRequirement::Tenant,
            (object) ['id' => 7],
            new ContextProvenance('test.tenant'),
            'tenant:7',
        );

        $invocation = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-sensitive-output',
            trustedContext: [$tenant, $actor],
            idempotencyKey: 'raw-retry-key',
            metadata: [
                'role' => 'admin',
                'tenant' => 'caller-tenant',
                'secret' => 'caller-metadata',
            ],
        );

        $context = OutputPolicyContext::fromInvocationContext($invocation);

        self::assertSame([$actor, $tenant], $context->allTrusted());
        self::assertTrue($context->has(ContextRequirement::AuthenticatedActor));
        self::assertTrue($context->has(ContextRequirement::Tenant));
        self::assertFalse($context->has(ContextRequirement::BrowserSession));
        self::assertSame($actor, $context->get(ContextRequirement::AuthenticatedActor));
        self::assertSame($tenant, $context->get(ContextRequirement::Tenant));
        self::assertNull($context->get(ContextRequirement::BrowserSession));
    }

    #[Test]
    public function caller_metadata_cannot_materialize_trusted_output_policy_authority(): void
    {
        $context = OutputPolicyContext::fromInvocationContext(new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-metadata-only',
            metadata: [
                'authenticated_actor' => 'caller-actor',
                'tenant' => 'caller-tenant',
                'browser_session' => 'caller-session',
            ],
        ));

        self::assertSame([], $context->allTrusted());
        self::assertFalse($context->has(ContextRequirement::AuthenticatedActor));
        self::assertFalse($context->has(ContextRequirement::Tenant));
        self::assertFalse($context->has(ContextRequirement::BrowserSession));
    }

    #[Test]
    public function it_does_not_expose_non_authoritative_invocation_fields(): void
    {
        $reflection = new ReflectionClass(OutputPolicyContext::class);

        foreach ([
            'invocationContext',
            'metadata',
            'surface',
            'correlationId',
            'idempotencyKey',
            'bindingId',
            'confirmationReceipt',
        ] as $forbiddenAccessor) {
            self::assertFalse(
                $reflection->hasMethod($forbiddenAccessor),
                sprintf('OutputPolicyContext must not expose %s().', $forbiddenAccessor),
            );
            self::assertFalse(
                $reflection->hasProperty($forbiddenAccessor),
                sprintf('OutputPolicyContext must not expose $%s.', $forbiddenAccessor),
            );
        }
    }
}
