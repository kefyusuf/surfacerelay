<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Result\ActionResultStatus;
use SurfaceRelay\LaravelMcp\Invocation\McpActionGateway;
use SurfaceRelay\LaravelMcp\Invocation\McpInvocationMetadata;
use SurfaceRelay\LaravelMcp\Tests\Support\CapturingActionPipelineAuditor;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestRuntime;

final class McpActionGatewayTest extends TestCase
{
    public function test_gateway_builds_exact_mcp_action_call_from_trusted_runtime_and_bounded_metadata(): void
    {
        $definition = McpTestRuntime::definition('orders.find', 3);
        $auditor = new CapturingActionPipelineAuditor();
        $gateway = new McpActionGateway(
            McpTestRuntime::bus($definition, $auditor, ['ok' => true]),
            new ActionResultNormalizer(),
            McpTestRuntime::composer('trusted-user', 'trusted-tenant'),
        );

        $result = $gateway->invoke(
            $definition,
            ['orderId' => '42', 'tenant_id' => 'attacker', 'actor' => 'attacker'],
            McpInvocationMetadata::from([
                McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => 'receipt-candidate',
                McpInvocationMetadata::IDEMPOTENCY_KEY => 'idem-candidate',
                'other/vendor' => 'ignored',
            ]),
        );

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertCount(1, $auditor->calls);

        $call = $auditor->calls[0];
        self::assertSame('orders.find', $call->actionId);
        self::assertSame(3, $call->actionVersion);
        self::assertSame(
            ['orderId' => '42', 'tenant_id' => 'attacker', 'actor' => 'attacker'],
            $call->input,
        );
        self::assertNull($call->bindingId);
        self::assertSame('receipt-candidate', $call->confirmationReceipt);

        self::assertSame('mcp', $call->context->surface);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $call->context->correlationId);
        self::assertSame('idem-candidate', $call->context->idempotencyKey);
        self::assertSame([], $call->context->metadata);
        self::assertSame(
            'trusted-user',
            $call->context->require(ContextRequirement::AuthenticatedActor)->value,
        );
        self::assertSame(
            'trusted-tenant',
            $call->context->require(ContextRequirement::Tenant)->value,
        );
    }

    public function test_caller_actor_and_tenant_fields_never_replace_resolver_authority(): void
    {
        $definition = McpTestRuntime::definition();
        ['gateway' => $gateway, 'auditor' => $auditor] = McpTestRuntime::gateway(
            $definition,
            actor: 'server-user',
            tenant: 'server-tenant',
        );

        $gateway->invoke(
            $definition,
            ['actor' => 'caller-user', 'tenant_id' => 'caller-tenant'],
            McpInvocationMetadata::from(null),
        );

        $context = $auditor->calls[0]->context;

        self::assertSame('server-user', $context->require(ContextRequirement::AuthenticatedActor)->value);
        self::assertSame('server-tenant', $context->require(ContextRequirement::Tenant)->value);
    }

    public function test_caller_current_record_does_not_satisfy_missing_trusted_context(): void
    {
        $definition = McpTestRuntime::definition(
            requirements: [ContextRequirement::CurrentRecord],
        );
        ['gateway' => $gateway] = McpTestRuntime::gateway($definition);

        $result = $gateway->invoke(
            $definition,
            ['current_record' => ['id' => 999]],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame('required_context_missing', $result->error?->code);
        self::assertSame(
            ['requirements' => ['current_record']],
            $result->error?->details,
        );
    }

    public function test_caller_current_selection_does_not_satisfy_missing_trusted_context(): void
    {
        $definition = McpTestRuntime::definition(
            requirements: [ContextRequirement::CurrentSelection],
        );
        ['gateway' => $gateway] = McpTestRuntime::gateway($definition);

        $result = $gateway->invoke(
            $definition,
            ['current_selection' => [10, 11]],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame('required_context_missing', $result->error?->code);
        self::assertSame(
            ['requirements' => ['current_selection']],
            $result->error?->details,
        );
    }

    public function test_confirmed_boolean_does_not_create_human_confirmation_authority(): void
    {
        $definition = McpTestRuntime::definition(
            requirements: [ContextRequirement::HumanConfirmation],
        );
        ['gateway' => $gateway, 'auditor' => $auditor] = McpTestRuntime::gateway($definition);

        $gateway->invoke(
            $definition,
            ['confirmed' => true],
            McpInvocationMetadata::from(null),
        );

        self::assertFalse(
            $auditor->calls[0]->context->has(ContextRequirement::HumanConfirmation),
        );
        self::assertSame(['confirmed' => true], $auditor->calls[0]->input);
    }
}
