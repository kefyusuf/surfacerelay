<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\DuplicateTrustedContext;
use SurfaceRelay\Laravel\Runtime\Context\DuplicateTrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtensionNotAvailable;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextNotAvailable;

/**
 * Runtime-authoritative context for one invocation. Physically separates
 * frozen ContextRequirement entries and namespaced trusted runtime extensions
 * from non-authoritative invocation metadata.
 *
 * Core invariant (implements accepted D-007/D-027/D-050): ordinary action
 * input and generic metadata never automatically become actor, tenant,
 * current record/selection, confirmation, or adapter-specific trusted
 * authority merely because payload keys have matching names. There is no
 * hydration/fallback from caller data to either trusted collection.
 *
 * `surface` and `correlationId` are origin/diagnostic labels, never
 * authorization. `idempotencyKey` is invocation metadata, not trusted
 * context. Browser-session values remain runtime-defined opaque context.
 */
final readonly class InvocationContext
{
    /** @var array<string, TrustedContextEntry> keyed by ContextRequirement value */
    private array $trustedEntries;

    /** @var list<TrustedContextEntry> canonical ContextRequirement declaration order */
    private array $trustedOrder;

    /** @var array<string, TrustedContextExtension> keyed by exact namespaced extension key */
    private array $trustedExtensionEntries;

    /** @var list<TrustedContextExtension> exact-key byte order */
    private array $trustedExtensionOrder;

    /**
     * @param list<TrustedContextEntry> $trustedContext
     * @param array<string, mixed> $metadata non-authoritative invocation metadata
     * @param list<TrustedContextExtension> $trustedExtensions adapter/runtime-specific trusted authority
     */
    public function __construct(
        public string $surface,
        public string $correlationId,
        array $trustedContext = [],
        public ?string $idempotencyKey = null,
        public array $metadata = [],
        array $trustedExtensions = [],
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

        $extensions = [];
        foreach ($trustedExtensions as $extension) {
            if (!$extension instanceof TrustedContextExtension) {
                throw new \InvalidArgumentException(
                    'trustedExtensions must only contain TrustedContextExtension instances.'
                );
            }
            if (isset($extensions[$extension->key])) {
                throw new DuplicateTrustedContextExtension($extension->key);
            }
            $extensions[$extension->key] = $extension;
        }
        ksort($extensions, SORT_STRING);
        $this->trustedExtensionEntries = $extensions;
        $this->trustedExtensionOrder = array_values($extensions);
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

    public function hasTrustedExtension(string $key): bool
    {
        return isset($this->trustedExtensionEntries[$key]);
    }

    public function getTrustedExtension(string $key): ?TrustedContextExtension
    {
        return $this->trustedExtensionEntries[$key] ?? null;
    }

    public function requireTrustedExtension(string $key): TrustedContextExtension
    {
        return $this->trustedExtensionEntries[$key]
            ?? throw new TrustedContextExtensionNotAvailable($key);
    }

    /** @return list<TrustedContextExtension> */
    public function allTrustedExtensions(): array
    {
        return $this->trustedExtensionOrder;
    }

    public function withTrustedEntry(TrustedContextEntry $entry): self
    {
        return new self(
            surface: $this->surface,
            correlationId: $this->correlationId,
            trustedContext: [...$this->trustedOrder, $entry],
            idempotencyKey: $this->idempotencyKey,
            metadata: $this->metadata,
            trustedExtensions: $this->trustedExtensionOrder,
        );
    }

    public function withTrustedExtension(TrustedContextExtension $extension): self
    {
        return new self(
            surface: $this->surface,
            correlationId: $this->correlationId,
            trustedContext: $this->trustedOrder,
            idempotencyKey: $this->idempotencyKey,
            metadata: $this->metadata,
            trustedExtensions: [...$this->trustedExtensionOrder, $extension],
        );
    }
}
