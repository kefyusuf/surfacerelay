<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Context;

/**
 * Adapter/runtime-specific trusted authority outside the frozen core
 * ContextRequirement vocabulary.
 *
 * Extensions are created only by trusted runtime code. They are physically
 * separate from InvocationContext metadata so caller-controlled metadata can
 * never become authority merely by using a matching namespaced key.
 */
final readonly class TrustedContextExtension
{
    private const string KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';

    public function __construct(
        public string $key,
        public mixed $value,
        public ContextProvenance $provenance,
        public ?string $scopeKey = null,
    ) {
        if (preg_match(self::KEY_PATTERN, $this->key) !== 1) {
            throw new \InvalidArgumentException(
                'Trusted context extension key must be a valid namespaced identifier.'
            );
        }

        if ($this->value === null) {
            throw new \InvalidArgumentException('Trusted context extension value must not be null.');
        }

        if ($this->scopeKey === '') {
            throw new \InvalidArgumentException(
                'Trusted context extension scopeKey must be null or a non-empty string.'
            );
        }
    }
}
