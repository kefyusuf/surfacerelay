<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\OutputPolicy;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

/**
 * Restricted trusted context available to output policy. It intentionally
 * retains only runtime-authoritative typed entries and never the full
 * InvocationContext or its generic invocation metadata/labels.
 */
final readonly class OutputPolicyContext
{
    /** @var list<TrustedContextEntry> */
    private array $trustedOrder;

    /** @var array<string, TrustedContextEntry> */
    private array $trustedByRequirement;

    /** @param list<TrustedContextEntry> $trusted */
    private function __construct(array $trusted)
    {
        $byRequirement = [];
        foreach ($trusted as $entry) {
            $byRequirement[$entry->requirement->value] = $entry;
        }

        $this->trustedOrder = array_values($trusted);
        $this->trustedByRequirement = $byRequirement;
    }

    public static function fromInvocationContext(InvocationContext $context): self
    {
        return new self($context->allTrusted());
    }

    /** @return list<TrustedContextEntry> */
    public function allTrusted(): array
    {
        return $this->trustedOrder;
    }

    public function has(ContextRequirement $requirement): bool
    {
        return isset($this->trustedByRequirement[$requirement->value]);
    }

    public function get(ContextRequirement $requirement): ?TrustedContextEntry
    {
        return $this->trustedByRequirement[$requirement->value] ?? null;
    }
}
