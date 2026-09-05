<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Canonical public representation of one action invocation result, matching
 * the frozen spec/0.1 action-result contract with stricter shape invariants:
 *
 * - succeeded carries data (which may legitimately be null — execution
 *   occurred) and never an error/confirmation;
 * - rejected/failed carry an error and never data/confirmation;
 * - confirmation_required carries a real ConfirmationChallenge and never
 *   data/error (fake challenges are never fabricated by the normalizer).
 *
 * The constructor is private; only status factories can create instances, so
 * ambiguous combinations are unrepresentable. correlationId is supplied by
 * the caller (the runtime passes InvocationContext.correlationId) and must be
 * non-empty; action IDs, binding IDs, and idempotency keys are not
 * correlation IDs. `meta` is explicit caller-supplied metadata only — the
 * runtime never auto-projects trusted context (actor, tenant, records,
 * selection, session, receipts, provenance, tokens) into it.
 */
final readonly class ActionResult implements \JsonSerializable
{
    /** @param array<string, mixed> $meta */
    private function __construct(
        public readonly ActionResultStatus $status,
        public readonly string $correlationId,
        public readonly mixed $data = null,
        public readonly ?ActionError $error = null,
        public readonly ?ConfirmationChallenge $confirmation = null,
        public readonly array $meta = [],
    ) {
        if ($this->correlationId === '') {
            throw new \InvalidArgumentException('ActionResult correlationId must be a non-empty string.');
        }
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function succeeded(string $correlationId, mixed $data = null, array $meta = []): self
    {
        return new self(ActionResultStatus::Succeeded, $correlationId, data: $data, meta: $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function rejected(string $correlationId, ActionError $error, array $meta = []): self
    {
        return new self(ActionResultStatus::Rejected, $correlationId, error: $error, meta: $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function failed(string $correlationId, ActionError $error, array $meta = []): self
    {
        return new self(ActionResultStatus::Failed, $correlationId, error: $error, meta: $meta);
    }

    /**
     * @param array<string, mixed> $meta
     */
    public static function confirmationRequired(string $correlationId, ConfirmationChallenge $confirmation, array $meta = []): self
    {
        return new self(ActionResultStatus::ConfirmationRequired, $correlationId, confirmation: $confirmation, meta: $meta);
    }

    /**
     * Deterministic serialization: status, correlationId, then exactly one of
     * data/error/confirmation, then meta only when non-empty.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'status' => $this->status->value,
            'correlationId' => $this->correlationId,
        ];

        $result = match ($this->status) {
            ActionResultStatus::Succeeded => [...$result, 'data' => $this->data],
            ActionResultStatus::Rejected, ActionResultStatus::Failed => [...$result, 'error' => $this->error?->toArray()],
            ActionResultStatus::ConfirmationRequired => [...$result, 'confirmation' => $this->confirmation?->toArray()],
        };

        if ($this->meta !== []) {
            $result['meta'] = $this->meta;
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
