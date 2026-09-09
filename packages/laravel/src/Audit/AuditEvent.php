<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use DateTimeImmutable;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;

final readonly class AuditEvent
{
    /** @param list<AuditTrustedContextEntry> $trustedContextManifest */
    public function __construct(
        public string $eventId,
        public DateTimeImmutable $recordedAt,
        public string $correlationId,
        public string $surface,
        public string $actionId,
        public int $actionVersion,
        public ActionScope $actionScope,
        public ActionEffect $actionEffect,
        public ActionRisk $actionRisk,
        public IdempotencyPolicy $idempotencyPolicy,
        public OutputSensitivity $outputSensitivity,
        public OutputContentTrust $outputContentTrust,
        public AuditOutcomeKind $outcomeKind,
        public ?ActionPipelineStage $haltedAt,
        public ?string $haltCode,
        public bool $humanConfirmationPresent,
        public array $trustedContextManifest,
    ) {
        if (preg_match('/^[0-9a-f]{32}$/', $this->eventId) !== 1) {
            throw new \InvalidArgumentException('Audit eventId must be 32 lowercase hexadecimal characters.');
        }
        if ($this->recordedAt->getTimezone()->getName() !== 'UTC') {
            throw AuditConfigurationViolation::clockMustReturnUtc();
        }
        if ($this->correlationId === '' || $this->surface === '') {
            throw new \InvalidArgumentException('Audit invocation labels must be non-empty.');
        }
        if ($this->actionId === '' || $this->actionVersion < 1) {
            throw new \InvalidArgumentException('Audit action identity must be valid.');
        }
        if ($this->outcomeKind === AuditOutcomeKind::Completed
            && ($this->haltedAt !== null || $this->haltCode !== null)) {
            throw new \InvalidArgumentException('Completed audit event cannot carry halt data.');
        }
        if ($this->outcomeKind === AuditOutcomeKind::Halted
            && ($this->haltCode === null || $this->haltCode === '')) {
            throw new \InvalidArgumentException('Halted audit event requires a non-empty halt code.');
        }
        foreach ($this->trustedContextManifest as $entry) {
            if (!$entry instanceof AuditTrustedContextEntry) {
                throw new \InvalidArgumentException(
                    'Audit trusted-context manifest must contain only AuditTrustedContextEntry values.'
                );
            }
        }
    }
}
