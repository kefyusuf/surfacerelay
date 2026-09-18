<?php

declare(strict_types=1);

namespace SurfaceRelay\LaravelMcp\Invocation;

final class InvalidMcpInvocationMetadata extends \InvalidArgumentException
{
    public static function confirmationReceiptType(mixed $value): self
    {
        return new self(sprintf(
            'MCP metadata "%s" must be a string when present; received %s.',
            McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY,
            get_debug_type($value),
        ));
    }

    public static function confirmationReceiptLength(int $length): self
    {
        return new self(sprintf(
            'MCP metadata "%s" must be at most 4096 characters; received %d.',
            McpInvocationMetadata::CONFIRMATION_RECEIPT_KEY,
            $length,
        ));
    }

    public static function idempotencyKeyType(mixed $value): self
    {
        return new self(sprintf(
            'MCP metadata "%s" must be a non-empty string when present; received %s.',
            McpInvocationMetadata::IDEMPOTENCY_KEY,
            get_debug_type($value),
        ));
    }

    public static function idempotencyKeyLength(int $length): self
    {
        return new self(sprintf(
            'MCP metadata "%s" must be between 1 and 240 characters; received %d.',
            McpInvocationMetadata::IDEMPOTENCY_KEY,
            $length,
        ));
    }
}
