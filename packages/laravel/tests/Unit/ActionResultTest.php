<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Result\ActionError;
use SurfaceRelay\Laravel\Result\ActionResult;
use SurfaceRelay\Laravel\Result\ActionResultStatus;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

final class ActionResultTest extends TestCase
{
    public function test_succeeded_preserves_data_and_correlation_id(): void
    {
        $result = ActionResult::succeeded('corr-123', ['itemId' => 'item-1']);

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertSame('corr-123', $result->correlationId);
        self::assertSame(['itemId' => 'item-1'], $result->data);
        self::assertNull($result->error);
        self::assertNull($result->confirmation);
        self::assertSame([], $result->meta);
    }

    public function test_successful_null_output_keeps_data_key_present(): void
    {
        $result = ActionResult::succeeded('corr-null', null);

        self::assertSame(ActionResultStatus::Succeeded, $result->status);
        self::assertNull($result->data);
        self::assertSame([
            'status' => 'succeeded',
            'correlationId' => 'corr-null',
            'data' => null,
        ], $result->toArray(), 'data must be present even when null: execution occurred.');
    }

    public function test_rejected_contains_error_only(): void
    {
        $error = new ActionError('required_context_missing', 'Required trusted context is unavailable.');
        $result = ActionResult::rejected('corr-1', $error);

        self::assertSame(ActionResultStatus::Rejected, $result->status);
        self::assertSame($error, $result->error);
        self::assertNull($result->data);
        self::assertNull($result->confirmation);
        self::assertSame([
            'status' => 'rejected',
            'correlationId' => 'corr-1',
            'error' => [
                'code' => 'required_context_missing',
                'message' => 'Required trusted context is unavailable.',
            ],
        ], $result->toArray());
    }

    public function test_failed_contains_error_only(): void
    {
        $error = new ActionError('execution_failed', 'Execution failed.');
        $result = ActionResult::failed('corr-2', $error, meta: ['attempt' => 2]);

        self::assertSame(ActionResultStatus::Failed, $result->status);
        self::assertSame($error, $result->error);
        self::assertSame([
            'status' => 'failed',
            'correlationId' => 'corr-2',
            'error' => ['code' => 'execution_failed', 'message' => 'Execution failed.'],
            'meta' => ['attempt' => 2],
        ], $result->toArray());
    }

    public function test_confirmation_required_contains_challenge_only(): void
    {
        $challenge = new ConfirmationChallenge('challenge-1', 'Approve refund of 500 TRY', '2026-09-07T00:00:00Z');
        $result = ActionResult::confirmationRequired('corr-3', $challenge);

        self::assertSame(ActionResultStatus::ConfirmationRequired, $result->status);
        self::assertSame($challenge, $result->confirmation);
        self::assertNull($result->error);
        self::assertNull($result->data);
        self::assertSame([
            'status' => 'confirmation_required',
            'correlationId' => 'corr-3',
            'confirmation' => [
                'challengeId' => 'challenge-1',
                'summary' => 'Approve refund of 500 TRY',
                'expiresAt' => '2026-09-07T00:00:00Z',
            ],
        ], $result->toArray());
    }

    public function test_non_empty_meta_is_preserved_and_empty_meta_omitted(): void
    {
        $withMeta = ActionResult::succeeded('corr-1', 'x', meta: ['surface' => 'webmcp']);
        $withoutMeta = ActionResult::succeeded('corr-1', 'x');

        self::assertSame(['surface' => 'webmcp'], $withMeta->meta);
        self::assertArrayHasKey('meta', $withMeta->toArray());
        self::assertArrayNotHasKey('meta', $withoutMeta->toArray());
    }

    public function test_error_details_omitted_when_null(): void
    {
        $withDetails = new ActionError('input_validation_failed', 'Input validation failed.', ['fields' => ['email']]);
        $withoutDetails = new ActionError('authorization_denied', 'Authorization denied.');

        self::assertSame(
            ['code' => 'input_validation_failed', 'message' => 'Input validation failed.', 'details' => ['fields' => ['email']]],
            $withDetails->toArray(),
        );
        self::assertArrayNotHasKey('details', $withoutDetails->toArray());
    }

    public function test_there_is_no_public_path_to_ambiguous_status_shapes(): void
    {
        $reflection = new \ReflectionClass(ActionResult::class);
        $constructor = $reflection->getConstructor();

        self::assertNotNull($constructor);
        self::assertTrue(
            $constructor->isPrivate(),
            'ActionResult must be constructed only through status factories so ambiguous shapes are unrepresentable.',
        );
        foreach (['succeeded', 'rejected', 'failed', 'confirmationRequired'] as $factory) {
            self::assertTrue($reflection->hasMethod($factory), "Missing factory: $factory");
        }
    }

    public function test_empty_correlation_id_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('correlationId');
        ActionResult::succeeded('');
    }

    public function test_json_serialization_matches_to_array(): void
    {
        $result = ActionResult::succeeded('corr-1', null);

        self::assertSame($result->toArray(), $result->jsonSerialize());
    }
}
