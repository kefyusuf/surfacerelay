<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class ActionCallTest extends TestCase
{
    public function test_confirmation_candidates_are_explicit_and_never_grant_trusted_authority(): void
    {
        $context = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-1',
            metadata: [
                'confirmationReceipt' => 'attacker-metadata',
                'human_confirmation' => true,
            ],
        );

        $call = new ActionCall(
            actionId: 'orders.refund.commit',
            actionVersion: 1,
            input: ['confirmed' => true],
            context: $context,
            bindingId: 'binding-1',
            confirmationReceipt: 'candidate-token',
        );

        self::assertSame('binding-1', $call->bindingId);
        self::assertSame('candidate-token', $call->confirmationReceipt);
        self::assertFalse($call->context->has(ContextRequirement::HumanConfirmation));
    }

    public function test_frozen_invocation_field_limits_are_preserved_without_interpreting_receipt_authority(): void
    {
        $context = new InvocationContext('webmcp', 'corr-1');

        $emptyReceipt = new ActionCall(
            'orders.refund.commit',
            1,
            [],
            $context,
            bindingId: null,
            confirmationReceipt: '',
        );
        self::assertSame('', $emptyReceipt->confirmationReceipt,
            'The schema permits an empty candidate; verification decides whether it is a real receipt.');

        $this->expectException(\InvalidArgumentException::class);
        new ActionCall(
            'orders.refund.commit',
            1,
            [],
            $context,
            bindingId: str_repeat('b', 241),
        );
    }

    public function test_pipeline_state_preserves_confirmation_candidates_across_immutable_transformations(): void
    {
        $context = new InvocationContext('webmcp', 'corr-1');
        $state = new ActionPipelineState(
            definition: $this->definition(),
            input: ['raw' => true],
            context: $context,
            bindingId: 'binding-1',
            confirmationReceipt: 'receipt-candidate',
        );

        $validated = $state->withInput(['validated' => true]);
        $completed = $validated->withOutput(['ok' => true]);

        self::assertSame('binding-1', $validated->bindingId);
        self::assertSame('receipt-candidate', $validated->confirmationReceipt);
        self::assertSame('binding-1', $completed->bindingId);
        self::assertSame('receipt-candidate', $completed->confirmationReceipt);
    }

    public function test_with_trusted_entry_returns_new_context_and_preserves_non_authoritative_invocation_metadata(): void
    {
        $actor = new TrustedContextEntry(
            ContextRequirement::AuthenticatedActor,
            'actor-1',
            new ContextProvenance('test.auth'),
        );
        $tenant = new TrustedContextEntry(
            ContextRequirement::Tenant,
            'tenant-1',
            new ContextProvenance('test.tenant'),
        );
        $original = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-1',
            trustedContext: [$actor],
            idempotencyKey: 'idem-1',
            metadata: ['locale' => 'tr-TR'],
        );

        $extended = $original->withTrustedEntry($tenant);

        self::assertNotSame($original, $extended);
        self::assertFalse($original->has(ContextRequirement::Tenant));
        self::assertSame('actor-1', $extended->require(ContextRequirement::AuthenticatedActor)->value);
        self::assertSame('tenant-1', $extended->require(ContextRequirement::Tenant)->value);
        self::assertSame($original->surface, $extended->surface);
        self::assertSame($original->correlationId, $extended->correlationId);
        self::assertSame($original->idempotencyKey, $extended->idempotencyKey);
        self::assertSame($original->metadata, $extended->metadata);
    }

    private function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund.commit',
            version: 1,
            title: 'Commit refund',
            description: 'Commits an approved refund.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::AuthenticatedActor],
        );
    }
}
