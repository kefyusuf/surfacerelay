<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\DuplicateTrustedContext;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextNotAvailable;

/**
 * Runtime-authoritative context for one invocation. Physically separates
 * trusted runtime authority (ContextRequirement-typed entries resolved by
 * runtime resolvers/adapters) from non-authoritative invocation metadata.
 *
 * Core invariant (implements accepted D-007): ordinary action input must
 * never automatically become actor, tenant, current record, current
 * selection, browser session, or confirmation authority merely because
 * payload keys have matching names. There is no hydration from caller
 * payload and no fallback from `metadata` (or any other non-authoritative
 * source) to trusted context — lookups fail closed.
 *
 * `surface` and `correlationId` are origin/diagnostic labels, never
 * authorization. `idempotencyKey` is invocation metadata (permitted by the
 * invocation contract), not a ContextRequirement and not authority.
 * `metadata` is non-authoritative invocation metadata: a metadata key named
 * `tenant` does not make ContextRequirement::Tenant available.
 *
 * Confirmation, when present, is opaque trusted runtime data; real receipt
 * semantics (issuance, signing, expiry, replay) belong to a later task.
 * Browser-session values remain runtime-defined opaque context.
 */
final readonly class InvocationContext
{
    /** @var array<string, TrustedContextEntry> keyed by ContextRequirement value */
    private array $trustedEntries;

    /** @var list<TrustedContextEntry> canonical ContextRequirement declaration order */
    private array $trustedOrder;

    /**
     * @param list<TrustedContextEntry> $trustedContext
     * @param array<string, mixed> $metadata non-authoritative invocation metadata
     */
    public function __construct(
        public string $surface,
        public string $correlationId,
        array $trustedContext = [],
        public ?string $idempotencyKey = null,
        public array $metadata = [],
    ) {
        if ($this->surface === '') {
            throw new \InvalidArgumentException('InvocationContext surface must be a non-empty string.');
        }
        if ($this->correlationId === '') {
            throw new \InvalidArgumentException('InvocationContext correlationId must be a non-empty string.');
        }

        $entries = [];
        foreach ($trustedContext as $entry) {
            if (!$entry instanceof TrustedContextEntry) {
                throw new \InvalidArgumentException(
                    'trustedContext must only contain TrustedContextEntry instances.'
                );
            }
            $key = $entry->requirement->value;
            if (isset($entries[$key])) {
                throw new DuplicateTrustedContext($entry->requirement);
            }
            $entries[$key] = $entry;
        }

        $ordered = [];
        foreach (ContextRequirement::cases() as $requirement) {
            if (isset($entries[$requirement->value])) {
                $ordered[] = $entries[$requirement->value];
            }
        }
        $this->trustedOrder = $ordered;
        $this->trustedEntries = $entries;
    }

    public function has(ContextRequirement $requirement): bool
    {
        return isset($this->trustedEntries[$requirement->value]);
    }

    public function get(ContextRequirement $requirement): ?TrustedContextEntry
    {
        return $this->trustedEntries[$requirement->value] ?? null;
    }

    public function require(ContextRequirement $requirement): TrustedContextEntry
    {
        return $this->trustedEntries[$requirement->value]
            ?? throw new TrustedContextNotAvailable($requirement);
    }

    /** @return list<TrustedContextEntry> */
    public function allTrusted(): array
    {
        return $this->trustedOrder;
    }
}
