<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Result\ActionResultStatus;
use SurfaceRelay\LaravelMcp\Invocation\McpInvocationMetadata;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestRuntime;

final class McpPipelineTrustIntegrationTest extends TestCase
{
    public function test_caller_tenant_and_actor_arguments_cannot_cross_trusted_context_boundary(): void
    {
        $definition = McpTestRuntime::pipelineDefinition('orders.inspect', 1);
        $runtime = McpTestRuntime::pipelineGateway(
            $definition,
            actor: 'SERVER_ACTOR_SECRET',
            tenant: 'SERVER_TENANT_SECRET',
        );

        $result = $runtime['gateway']->invoke(
            $definition,
            [
                'actor' => 'CALLER_ACTOR',
                'tenant_id' => 'CALLER_TENANT',
                'secret' => 'RAW_INPUT_SECRET',
            ],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertSame(1, $runtime['executor']->calls);

        $execution = $runtime['executor']->lastState;
        self::assertNotNull($execution);
        self::assertSame('CALLER_ACTOR', $execution->input['actor']);
        self::assertSame('CALLER_TENANT', $execution->input['tenant_id']);
        self::assertSame(
            'SERVER_ACTOR_SECRET',
            $execution->context->require(ContextRequirement::AuthenticatedActor)->value,
        );
        self::assertSame(
            'SERVER_TENANT_SECRET',
            $execution->context->require(ContextRequirement::Tenant)->value,
        );
    }

    public function test_caller_current_record_and_selection_cannot_materialize_missing_trusted_context(): void
    {
        foreach ([
            ContextRequirement::CurrentRecord => ['current_record' => ['id' => 99]],
            ContextRequirement::CurrentSelection => ['current_selection' => [99]],
        ] as $requirement => $input) {
            $requirement = ContextRequirement::from($requirement);
            $definition = McpTestRuntime::pipelineDefinition(
                'orders.context_probe',
                1,
                requirements: [$requirement],
            );
            $runtime = McpTestRuntime::pipelineGateway($definition);

            $result = $runtime['gateway']->invoke(
                $definition,
                $input,
                McpInvocationMetadata::from(null),
            );

            self::assertSame(ActionResultStatus::Rejected, $result->status);
            self::assertSame('required_context_missing', $result->error?->code);
            self::assertSame(0, $runtime['executor']->calls);
        }
    }

    public function test_discovery_eligibility_does_not_bypass_invocation_authorization(): void
    {
        $definition = McpTestRuntime::pipelineDefinition('orders.denied', 1);
        $runtime = McpTestRuntime::pipelineGateway(
            $definition,
            authorizationDenied: true,
        );

        $result = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame('authorization_denied', $result->error?->code);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_sensitive_output_fails_closed_without_redactor_and_never_releases_raw_secret(): void
    {
        $definition = McpTestRuntime::pipelineDefinition(
            'orders.secret',
            1,
            sensitivity: OutputSensitivity::Sensitive,
        );
        $runtime = McpTestRuntime::pipelineGateway(
            $definition,
            executionOutput: 'SECRET_RAW_MCP_OUTPUT',
        );

        $result = $runtime['gateway']->invoke(
            $definition,
            ['secret' => 'RAW_INPUT_SECRET'],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Failed, $result->status);
        self::assertSame('output_policy_failed', $result->error?->code);
        self::assertStringNotContainsString(
            'SECRET_RAW_MCP_OUTPUT',
            json_encode($result->toArray(), JSON_THROW_ON_ERROR),
        );
        self::assertSame(1, $runtime['executor']->calls);
    }

    public function test_sensitive_output_releases_only_explicit_redacted_value(): void
    {
        $definition = McpTestRuntime::pipelineDefinition(
            'orders.secret',
            2,
            sensitivity: OutputSensitivity::Sensitive,
        );
        $runtime = McpTestRuntime::pipelineGateway(
            $definition,
            executionOutput: 'SECRET_RAW_MCP_OUTPUT',
            releaseSensitiveOutput: ['safe' => 'released'],
        );

        $result = $runtime['gateway']->invoke(
            $definition,
            [],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertSame(['safe' => 'released'], $result->data);
        self::assertStringNotContainsString(
            'SECRET_RAW_MCP_OUTPUT',
            json_encode($result->toArray(), JSON_THROW_ON_ERROR),
        );
    }

    public function test_structured_audit_records_minimized_mcp_evidence_for_success_rejection_and_output_failure(): void
    {
        $cases = [
            'success' => [
                'definition' => McpTestRuntime::pipelineDefinition('orders.success', 1),
                'authorizationDenied' => false,
                'executionOutput' => ['ok' => true],
            ],
            'authorization' => [
                'definition' => McpTestRuntime::pipelineDefinition('orders.denied', 1),
                'authorizationDenied' => true,
                'executionOutput' => ['never' => 'executed'],
            ],
            'output_policy' => [
                'definition' => McpTestRuntime::pipelineDefinition(
                    'orders.secret',
                    1,
                    sensitivity: OutputSensitivity::Sensitive,
                ),
                'authorizationDenied' => false,
                'executionOutput' => 'SECRET_RAW_MCP_OUTPUT',
            ],
        ];

        foreach ($cases as $name => $case) {
            $runtime = McpTestRuntime::pipelineGateway(
                $case['definition'],
                actor: 'SERVER_ACTOR_SECRET',
                tenant: 'SERVER_TENANT_SECRET',
                authorizationDenied: $case['authorizationDenied'],
                executionOutput: $case['executionOutput'],
            );

            $runtime['gateway']->invoke(
                $case['definition'],
                ['secret' => 'RAW_INPUT_SECRET', 'actor' => 'CALLER_ACTOR'],
                McpInvocationMetadata::from([
                    McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => 'CONFIRMATION_RECEIPT_SECRET',
                    McpInvocationMetadata::IDEMPOTENCY_KEY => 'IDEMPOTENCY_KEY_SECRET',
                ]),
            );

            self::assertCount(1, $runtime['auditStore']->events, $name);

            $event = $runtime['auditStore']->events[0];
            self::assertSame('mcp', $event->surface, $name);
            self::assertSame($case['definition']->id, $event->actionId, $name);

            $serialized = McpTestRuntime::serializeAuditEvent($event);

            foreach ([
                'RAW_INPUT_SECRET',
                'CALLER_ACTOR',
                'CONFIRMATION_RECEIPT_SECRET',
                'IDEMPOTENCY_KEY_SECRET',
                'SERVER_ACTOR_SECRET',
                'SERVER_TENANT_SECRET',
                'SECRET_RAW_MCP_OUTPUT',
            ] as $forbidden) {
                self::assertStringNotContainsString($forbidden, $serialized, $name.' leaked '.$forbidden);
            }
        }
    }
}
