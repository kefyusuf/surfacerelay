<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Audit;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use JsonException;

final readonly class DatabaseAuditEventStore implements AuditEventStore
{
    public function __construct(
        private ConnectionInterface $connection,
        private string $table = 'surfacerelay_audit_events',
    ) {}

    public function append(AuditEvent $event): void
    {
        try {
            $manifest = json_encode(
                array_map(
                    static fn (AuditTrustedContextEntry $entry): array => [
                        'requirement' => $entry->requirement->value,
                        'provider' => $entry->provider,
                    ],
                    $event->trustedContextManifest,
                ),
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException) {
            throw AuditStoreUnavailable::encodingFailed();
        }

        $row = [
            'event_id' => $event->eventId,
            'recorded_at' => $event->recordedAt->format('Y-m-d H:i:s.u'),
            'correlation_id' => $event->correlationId,
            'correlation_hash' => hash('sha256', "surfacerelay.audit.correlation.v1\n" . $event->correlationId),
            'surface' => $event->surface,
            'action_id' => $event->actionId,
            'action_version' => $event->actionVersion,
            'action_scope' => $event->actionScope->value,
            'action_effect' => $event->actionEffect->value,
            'action_risk' => $event->actionRisk->value,
            'idempotency_policy' => $event->idempotencyPolicy->value,
            'output_sensitivity' => $event->outputSensitivity->value,
            'output_content_trust' => $event->outputContentTrust->value,
            'outcome_kind' => $event->outcomeKind->value,
            'halted_at' => $event->haltedAt?->value,
            'halt_code' => $event->haltCode,
            'halt_code_hash' => $event->haltCode === null
                ? null
                : hash('sha256', "surfacerelay.audit.halt-code.v1\n" . $event->haltCode),
            'human_confirmation_present' => $event->humanConfirmationPresent,
            'trusted_context_manifest' => $manifest,
        ];

        try {
            $this->connection->table($this->table)->insert($row);
        } catch (QueryException) {
            throw AuditStoreUnavailable::operationFailed();
        }
    }
}
