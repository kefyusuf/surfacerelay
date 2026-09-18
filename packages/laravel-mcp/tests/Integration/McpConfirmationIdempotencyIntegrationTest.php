<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Integration;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Result\ActionResultStatus;
use SurfaceRelay\LaravelMcp\Invocation\McpInvocationMetadata;
use SurfaceRelay\LaravelMcp\Tests\Support\McpTestRuntime;

final class McpConfirmationIdempotencyIntegrationTest extends TestCase
{
    public function test_consequential_call_without_receipt_requires_confirmation(): void
    {
        $runtime = McpTestRuntime::confirmationAuthorityRuntime();
        $definition = $runtime['definition'];

        $result = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::ConfirmationRequired, $result->status);
        self::assertNotNull($result->confirmation);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_confirmed_boolean_argument_does_not_bypass_confirmation(): void
    {
        $runtime = McpTestRuntime::confirmationAuthorityRuntime();
        $definition = $runtime['definition'];

        $result = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42', 'confirmed' => true],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::ConfirmationRequired, $result->status);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_unapproved_receipt_candidate_does_not_grant_authority(): void
    {
        $runtime = McpTestRuntime::confirmationAuthorityRuntime();
        $definition = $runtime['definition'];

        $first = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            McpInvocationMetadata::from(null),
        );
        $candidate = $first->confirmation?->challengeId;
        self::assertNotNull($candidate);

        $second = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            McpInvocationMetadata::from([
                McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => $candidate,
            ]),
        );

        self::assertSame(ActionResultStatus::ConfirmationRequired, $second->status);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_approved_exact_scope_receipt_allows_one_retry_and_consumed_receipt_cannot_be_reused(): void
    {
        $runtime = McpTestRuntime::confirmationAuthorityRuntime();
        $definition = $runtime['definition'];
        $input = ['orderId' => '42'];

        $pending = $runtime['gateway']->invoke(
            $definition,
            $input,
            McpInvocationMetadata::from(null),
        );
        $challengeId = $pending->confirmation?->challengeId;
        self::assertNotNull($challengeId);

        $receipt = $runtime['confirmationService']->approveChallenge($challengeId);
        self::assertNotNull($receipt);

        $approved = $runtime['gateway']->invoke(
            $definition,
            $input,
            McpInvocationMetadata::from([
                McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => $receipt,
            ]),
        );

        self::assertSame(ActionResultStatus::Succeeded, $approved->status);
        self::assertSame(1, $runtime['executor']->calls);

        $reused = $runtime['gateway']->invoke(
            $definition,
            $input,
            McpInvocationMetadata::from([
                McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => $receipt,
            ]),
        );

        self::assertSame(ActionResultStatus::ConfirmationRequired, $reused->status);
        self::assertSame(1, $runtime['executor']->calls);
    }

    public function test_required_idempotency_key_missing_from_mcp_metadata_is_rejected(): void
    {
        $runtime = McpTestRuntime::idempotencyAuthorityRuntime();
        $definition = $runtime['definition'];

        $result = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame('idempotency_key_required', $result->error?->code);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_business_argument_idempotency_key_does_not_satisfy_required_policy(): void
    {
        $runtime = McpTestRuntime::idempotencyAuthorityRuntime();
        $definition = $runtime['definition'];

        $result = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42', 'idempotencyKey' => 'caller-smuggled'],
            McpInvocationMetadata::from(null),
        );

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame('idempotency_key_required', $result->error?->code);
        self::assertSame(0, $runtime['executor']->calls);
    }

    public function test_namespaced_idempotency_metadata_allows_fresh_execution_and_exact_retry_replays(): void
    {
        $runtime = McpTestRuntime::idempotencyAuthorityRuntime();
        $definition = $runtime['definition'];
        $input = ['orderId' => '42'];
        $metadata = McpInvocationMetadata::from([
            McpInvocationMetadata::IDEMPOTENCY_KEY => 'idem-orders-42',
        ]);

        $fresh = $runtime['gateway']->invoke($definition, $input, $metadata);
        self::assertSame(ActionResultStatus::Succeeded, $fresh->status);
        self::assertSame(1, $runtime['executor']->calls);

        $replay = $runtime['gateway']->invoke($definition, $input, $metadata);

        self::assertSame(ActionResultStatus::Succeeded, $replay->status);
        self::assertSame($fresh->data, $replay->data);
        self::assertSame(1, $runtime['executor']->calls);
    }

    public function test_same_idempotency_key_with_different_input_is_rejected_as_conflict(): void
    {
        $runtime = McpTestRuntime::idempotencyAuthorityRuntime();
        $definition = $runtime['definition'];
        $metadata = McpInvocationMetadata::from([
            McpInvocationMetadata::IDEMPOTENCY_KEY => 'idem-orders-conflict',
        ]);

        $first = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '42'],
            $metadata,
        );
        self::assertSame(ActionResultStatus::Succeeded, $first->status);
        self::assertSame(1, $runtime['executor']->calls);

        $conflict = $runtime['gateway']->invoke(
            $definition,
            ['orderId' => '99'],
            $metadata,
        );

        self::assertSame(ActionResultStatus::Rejected, $conflict->status);
        self::assertSame('idempotency_conflict', $conflict->error?->code);
        self::assertSame(1, $runtime['executor']->calls);
    }
}
