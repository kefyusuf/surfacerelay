<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use SurfaceRelay\Laravel\Enums\ContextRequirement;

final readonly class AuditTrustedContextEntry
{
    private const string EXTENSION_KEY_PATTERN = '/^[a-z0-9.-]+\/[a-zA-Z0-9._-]+$/';

    public function __construct(
        public ?ContextRequirement $requirement,
        public string $provider,
        public ?string $extension = null,
    ) {
        if ($this->provider === '') {
            throw new \InvalidArgumentException('Audit trusted-context provider must be non-empty.');
        }

        if (($this->requirement === null) === ($this->extension === null)) {
            throw new \InvalidArgumentException(
                'Audit trusted-context entry must contain exactly one requirement or extension.'
            );
        }

        if ($this->extension !== null
            && preg_match(self::EXTENSION_KEY_PATTERN, $this->extension) !== 1) {
            throw new \InvalidArgumentException(
                'Audit trusted-context extension must be a valid namespaced identifier.'
            );
        }
    }

    public static function forRequirement(ContextRequirement $requirement, string $provider): self
    {
        return new self($requirement, $provider);
    }

    public static function forExtension(string $extension, string $provider): self
    {
        return new self(null, $provider, $extension);
    }
}
