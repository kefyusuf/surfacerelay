<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Result;

/**
 * Public, immutable error value for rejected/failed results.
 *
 * `code` is an extensible machine-readable lowercase string namespace
 * (D-030), never a closed enum; `message` is safe presentation text owned by
 * SurfaceRelay — never internal diagnostics, exception text, framework
 * denial messages, or stack traces. `details` is optional, JSON-friendly,
 * and strictly non-authoritative diagnostic/result data: no future stage may
 * read details back as trusted context or authorization evidence. Both
 * strings are preserved verbatim (no trimming/normalization).
 */
final readonly class ActionError implements \JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $message,
        public readonly mixed $details = null,
    ) {
        if ($this->code === '') {
            throw new \InvalidArgumentException('ActionError code must be a non-empty string.');
        }
        if ($this->message === '') {
            throw new \InvalidArgumentException('ActionError message must be a non-empty string.');
        }
    }

    /**
     * Deterministic serialization: code, message, then details only when set.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [
            'code' => $this->code,
            'message' => $this->message,
        ];
        if ($this->details !== null) {
            $result['details'] = $this->details;
        }

        return $result;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
