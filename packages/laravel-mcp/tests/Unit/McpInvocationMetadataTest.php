<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\LaravelMcp\Invocation\InvalidMcpInvocationMetadata;
use SurfaceRelay\LaravelMcp\Invocation\McpInvocationMetadata;

final class McpInvocationMetadataTest extends TestCase
{
    public function test_absent_and_unknown_metadata_produce_no_surface_control_candidates(): void
    {
        foreach ([null, [], ['other/vendor' => 'ignored']] as $meta) {
            $parsed = McpInvocationMetadata::from($meta);

            self::assertNull($parsed->confirmationReceipt);
            self::assertNull($parsed->idempotencyKey);
        }
    }

    public function test_valid_namespaced_candidates_are_preserved_exactly(): void
    {
        $parsed = McpInvocationMetadata::from([
            McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => 'receipt-abc',
            McpInvocationMetadata::IDEMPOTENCY_KEY => 'idem-123',
            'other/vendor' => ['ignored' => true],
        ]);

        self::assertSame('receipt-abc', $parsed->confirmationReceipt);
        self::assertSame('idem-123', $parsed->idempotencyKey);
    }

    public function test_confirmation_candidate_must_be_string_and_at_most_4096_characters(): void
    {
        foreach ([123, true, [], new \stdClass()] as $invalid) {
            try {
                McpInvocationMetadata::from([
                    McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => $invalid,
                ]);
                self::fail('Expected non-string confirmation candidate to fail closed.');
            } catch (InvalidMcpInvocationMetadata $exception) {
                self::assertStringContainsString('confirmationReceipt', $exception->getMessage());
            }
        }

        self::assertSame(
            4096,
            mb_strlen(McpInvocationMetadata::from([
                McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => str_repeat('ü', 4096),
            ])->confirmationReceipt ?? '', 'UTF-8'),
        );

        $this->expectException(InvalidMcpInvocationMetadata::class);
        McpInvocationMetadata::from([
            McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY => str_repeat('ü', 4097),
        ]);
    }

    public function test_idempotency_candidate_must_be_non_empty_string_at_most_240_characters(): void
    {
        foreach ([123, false, [], '', new \stdClass()] as $invalid) {
            try {
                McpInvocationMetadata::from([
                    McpInvocationMetadata::IDEMPOTENCY_KEY => $invalid,
                ]);
                self::fail('Expected invalid idempotency candidate to fail closed.');
            } catch (InvalidMcpInvocationMetadata $exception) {
                self::assertStringContainsString('idempotencyKey', $exception->getMessage());
            }
        }

        self::assertSame(
            240,
            mb_strlen(McpInvocationMetadata::from([
                McpInvocationMetadata::IDEMPOTENCY_KEY => str_repeat('ü', 240),
            ])->idempotencyKey ?? '', 'UTF-8'),
        );

        $this->expectException(InvalidMcpInvocationMetadata::class);
        McpInvocationMetadata::from([
            McpInvocationMetadata::IDEMPOTENCY_KEY => str_repeat('ü', 241),
        ]);
    }
}
