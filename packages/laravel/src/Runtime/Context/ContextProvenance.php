<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * Diagnostic/audit information about which runtime provider resolved a
 * trusted context entry. Provenance is not caller authority by itself.
 * Providers remain an extensible open vocabulary (adapters/resolvers differ);
 * no closed enum, timestamps, signatures, or receipt verification here.
 */
final readonly class ContextProvenance
{
    public function __construct(
        public readonly string $provider,
        public readonly ?string $reference = null,
    ) {
        if ($this->provider === '') {
            throw new \InvalidArgumentException(
                'ContextProvenance provider must be a non-empty string.'
            );
        }
    }
}
