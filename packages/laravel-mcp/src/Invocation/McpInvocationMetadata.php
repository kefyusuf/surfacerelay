<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Invocation;

/**
 * Bounded, non-authoritative invocation controls extracted from MCP _meta.
 *
 * Unknown metadata is deliberately ignored. Neither property is trusted
 * context or authorization evidence; downstream core stages still validate
 * and consume candidates according to their existing contracts.
 */
final readonly class McpInvocationMetadata
{
    public const string CONFIRMATION_RECEIPT_KEY = 'io.surfacerelay/confirmationReceipt';

    public const string IDEMPOTENCY_KEY = 'io.surfacerelay/idempotencyKey';

    public function __construct(
        public ?string $confirmationReceipt,
        public ?string $idempotencyKey,
    ) {}

    /**
     * @param array<string, mixed>|null $meta
     */
    public static function from(?array $meta): self
    {
        $confirmationReceipt = null;
        if ($meta !== null && array_key_exists(self::CONFIRMATION_RECEIPT_KEY, $meta)) {
            $candidate = $meta[self::CONFIRMATION_RECEIPT_KEY];

            if (!is_string($candidate)) {
                throw InvalidMcpInvocationMetadata::confirmationReceiptType($candidate);
            }

            $length = mb_strlen($candidate, 'UTF-8');
            if ($length > 4096) {
                throw InvalidMcpInvocationMetadata::confirmationReceiptLength($length);
            }

            $confirmationReceipt = $candidate;
        }

        $idempotencyKey = null;
        if ($meta !== null && array_key_exists(self::IDEMPOTENCY_KEY, $meta)) {
            $candidate = $meta[self::IDEMPOTENCY_KEY];

            if (!is_string($candidate)) {
                throw InvalidMcpInvocationMetadata::idempotencyKeyType($candidate);
            }

            $length = mb_strlen($candidate, 'UTF-8');
            if ($length < 1 || $length > 240) {
                throw InvalidMcpInvocationMetadata::idempotencyKeyLength($length);
            }

            $idempotencyKey = $candidate;
        }

        return new self($confirmationReceipt, $idempotencyKey);
    }
}
