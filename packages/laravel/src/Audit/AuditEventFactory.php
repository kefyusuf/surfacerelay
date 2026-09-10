<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextExtension;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;

final readonly class AuditEventFactory
{
    public function __construct(private AuditClock $clock) {}

    public function create(ActionPipelineOutcome $outcome): AuditEvent
    {
        $state = $outcome->state;
        $context = $state->context;
        $definition = $state->definition;
        $recordedAt = $this->clock->now();

        if ($recordedAt->getTimezone()->getName() !== 'UTC') {
            throw AuditConfigurationViolation::clockMustReturnUtc();
        }

        $manifest = array_map(
            static fn (TrustedContextEntry $entry): AuditTrustedContextEntry =>
                AuditTrustedContextEntry::forRequirement(
                    $entry->requirement,
                    $entry->provenance->provider,
                ),
            $context->allTrusted(),
        );

        foreach ($context->allTrustedExtensions() as $extension) {
            $manifest[] = $this->extensionManifestEntry($extension);
        }

        return new AuditEvent(
            eventId: bin2hex(random_bytes(16)),
            recordedAt: $recordedAt,
            correlationId: $context->correlationId,
            surface: $context->surface,
            actionId: $definition->id,
            actionVersion: $definition->version,
            actionScope: $definition->scope,
            actionEffect: $definition->effect,
            actionRisk: $definition->risk,
            idempotencyPolicy: $definition->idempotency,
            outputSensitivity: $definition->outputSensitivity,
            outputContentTrust: $definition->outputContentTrust,
            outcomeKind: $outcome->completed ? AuditOutcomeKind::Completed : AuditOutcomeKind::Halted,
            haltedAt: $outcome->haltedAt,
            haltCode: $outcome->halt?->code,
            humanConfirmationPresent: $context->has(ContextRequirement::HumanConfirmation),
            trustedContextManifest: $manifest,
        );
    }

    private function extensionManifestEntry(TrustedContextExtension $extension): AuditTrustedContextEntry
    {
        return AuditTrustedContextEntry::forExtension(
            $extension->key,
            $extension->provenance->provider,
        );
    }
}
