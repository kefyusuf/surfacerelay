<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Pipeline;

use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Protocol-neutral invocation request. Action identity is always explicit
 * (id + version); there is no latest-version behavior or fallback. This
 * object is constructed by trusted runtime wiring, never hydrated from
 * HTTP/WebMCP/MCP payloads. `bindingId` is a runtime reference and
 * `confirmationReceipt` is an untrusted caller-supplied candidate; neither
 * field is trusted authority by presence alone.
 */
final readonly class ActionCall
{
    /** @param array<string, mixed> $input */
    public function __construct(
        public string $actionId,
        public int $actionVersion,
        public array $input,
        public InvocationContext $context,
        public ?string $bindingId = null,
        public ?string $confirmationReceipt = null,
    ) {
        if ($this->actionId === '') {
            throw new \InvalidArgumentException('ActionCall actionId must be a non-empty string.');
        }
        if ($this->actionVersion < 1) {
            throw new \InvalidArgumentException('ActionCall actionVersion must be >= 1.');
        }
        if ($this->bindingId !== null) {
            $length = mb_strlen($this->bindingId, 'UTF-8');
            if ($length < 1 || $length > 240) {
                throw new \InvalidArgumentException(
                    'ActionCall bindingId must be null or between 1 and 240 characters.'
                );
            }
        }
        if ($this->confirmationReceipt !== null
            && mb_strlen($this->confirmationReceipt, 'UTF-8') > 4096) {
            throw new \InvalidArgumentException(
                'ActionCall confirmationReceipt must be null or at most 4096 characters.'
            );
        }
    }
}
