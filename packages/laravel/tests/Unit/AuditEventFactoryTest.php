<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditConfigurationViolation;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\AuditOutcomeKind;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class AuditEventFactoryTest extends TestCase
{
    public function test_completed_event_projects_only_allowlisted_final_facts(): void
    {
        $this->assertAuditTypesExist();

        $definition = $this->definition(
            inputSchema: ['type' => 'object', 'secret_schema_marker' => 'SECRET_SCHEMA'],
            outputSchema: ['type' => 'object', 'secret_output_schema_marker' => 'SECRET_OUTPUT_SCHEMA'],
            extensions: ['example/secret' => 'SECRET_EXTENSION'],
        );
        $context = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-404',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::Tenant,
                    'SECRET_TENANT_VALUE',
                    new ContextProvenance('tenant.provider', 'SECRET_PROVENANCE_REFERENCE'),
                    confirmationScopeKey: 'SECRET_SCOPE_KEY',
                ),
            ],
            idempotencyKey: 'SECRET_IDEMPOTENCY_KEY',
            metadata: ['secret' => 'SECRET_METADATA'],
        );
        $state = new ActionPipelineState(
            definition: $definition,
            input: ['secret' => 'SECRET_INPUT'],
            context: $context,
            hasOutput: true,
            output: ['secret' => 'SECRET_OUTPUT'],
            bindingId: 'SECRET_BINDING',
            confirmationReceipt: 'SECRET_RECEIPT',
        );

        $event = $this->factory()->create(ActionPipelineOutcome::completed($state));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $event->eventId);
        self::assertSame('2026-09-09 16:20:30.123456', $event->recordedAt->format('Y-m-d H:i:s.u'));
        self::assertSame('UTC', $event->recordedAt->getTimezone()->getName());
        self::assertSame('corr-404', $event->correlationId);
        self::assertSame('webmcp', $event->surface);
        self::assertSame($definition->id, $event->actionId);
        self::assertSame($definition->version, $event->actionVersion);
        self::assertSame(ActionScope::Portable, $event->actionScope);
        self::assertSame(ActionEffect::ReversibleWrite, $event->actionEffect);
        self::assertSame(ActionRisk::Moderate, $event->actionRisk);
        self::assertSame(IdempotencyPolicy::RecommendedKey, $event->idempotencyPolicy);
        self::assertSame(OutputSensitivity::Sensitive, $event->outputSensitivity);
        self::assertSame(OutputContentTrust::ContainsUntrustedContent, $event->outputContentTrust);
        self::assertSame(AuditOutcomeKind::Completed, $event->outcomeKind);
        self::assertNull($event->haltedAt);
        self::assertNull($event->haltCode);
        self::assertFalse($event->humanConfirmationPresent);
        self::assertCount(1, $event->trustedContextManifest);
        self::assertSame(ContextRequirement::Tenant, $event->trustedContextManifest[0]->requirement);
        self::assertSame('tenant.provider', $event->trustedContextManifest[0]->provider);

        $persistableFacts = json_encode([
            'eventId' => $event->eventId,
            'recordedAt' => $event->recordedAt->format('Y-m-d H:i:s.u'),
            'correlationId' => $event->correlationId,
            'surface' => $event->surface,
            'actionId' => $event->actionId,
            'actionVersion' => $event->actionVersion,
            'actionScope' => $event->actionScope->value,
            'actionEffect' => $event->actionEffect->value,
            'actionRisk' => $event->actionRisk->value,
            'idempotencyPolicy' => $event->idempotencyPolicy->value,
            'outputSensitivity' => $event->outputSensitivity->value,
            'outputContentTrust' => $event->outputContentTrust->value,
            'outcomeKind' => $event->outcomeKind->value,
            'haltedAt' => $event->haltedAt?->value,
            'haltCode' => $event->haltCode,
            'humanConfirmationPresent' => $event->humanConfirmationPresent,
            'trustedContextManifest' => array_map(
                static fn ($entry): array => [
                    'requirement' => $entry->requirement->value,
                    'provider' => $entry->provider,
                ],
                $event->trustedContextManifest,
            ),
        ], JSON_THROW_ON_ERROR);

        foreach ([
            'SECRET_SCHEMA',
            'SECRET_OUTPUT_SCHEMA',
            'SECRET_EXTENSION',
            'SECRET_TENANT_VALUE',
            'SECRET_PROVENANCE_REFERENCE',
            'SECRET_SCOPE_KEY',
            'SECRET_IDEMPOTENCY_KEY',
            'SECRET_METADATA',
            'SECRET_INPUT',
            'SECRET_OUTPUT',
            'SECRET_BINDING',
            'SECRET_RECEIPT',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $persistableFacts);
        }
    }

    public function test_halted_event_records_stage_code_confirmation_and_canonical_manifest_without_details(): void
    {
        $this->assertAuditTypesExist();

        $context = new InvocationContext(
            surface: 'webmcp',
            correlationId: 'corr-halt',
            trustedContext: [
                new TrustedContextEntry(
                    ContextRequirement::HumanConfirmation,
                    true,
                    new ContextProvenance('surfacerelay.confirmation', 'SECRET_CONFIRMATION_REFERENCE'),
                    confirmationScopeKey: 'SECRET_CONFIRMATION_SCOPE',
                ),
                new TrustedContextEntry(
                    ContextRequirement::AuthenticatedActor,
                    'SECRET_ACTOR',
                    new ContextProvenance('laravel.auth', 'SECRET_ACTOR_REFERENCE'),
                ),
            ],
        );
        $state = new ActionPipelineState(
            definition: $this->definition(),
            input: [],
            context: $context,
        );
        $event = $this->factory()->create(ActionPipelineOutcome::halted(
            $state,
            ActionPipelineStage::OutputPolicy,
            new ActionPipelineHalt('output_policy_failed', ['secret' => 'SECRET_HALT_DETAILS']),
        ));

        self::assertSame(AuditOutcomeKind::Halted, $event->outcomeKind);
        self::assertSame(ActionPipelineStage::OutputPolicy, $event->haltedAt);
        self::assertSame('output_policy_failed', $event->haltCode);
        self::assertTrue($event->humanConfirmationPresent);
        self::assertSame(
            [ContextRequirement::AuthenticatedActor, ContextRequirement::HumanConfirmation],
            array_map(static fn ($entry) => $entry->requirement, $event->trustedContextManifest),
        );
        self::assertSame(['laravel.auth', 'surfacerelay.confirmation'], array_map(
            static fn ($entry): string => $entry->provider,
            $event->trustedContextManifest,
        ));

        $manifest = json_encode(array_map(
            static fn ($entry): array => [
                'requirement' => $entry->requirement->value,
                'provider' => $entry->provider,
            ],
            $event->trustedContextManifest,
        ), JSON_THROW_ON_ERROR);
        foreach ([
            'SECRET_HALT_DETAILS',
            'SECRET_CONFIRMATION_REFERENCE',
            'SECRET_CONFIRMATION_SCOPE',
            'SECRET_ACTOR',
            'SECRET_ACTOR_REFERENCE',
        ] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $manifest);
        }
    }

    public function test_pre_stage_context_halt_preserves_null_halted_at(): void
    {
        $this->assertAuditTypesExist();

        $event = $this->factory()->create(ActionPipelineOutcome::halted(
            new ActionPipelineState($this->definition(), [], $this->context()),
            null,
            new ActionPipelineHalt('required_context_missing'),
        ));

        self::assertSame(AuditOutcomeKind::Halted, $event->outcomeKind);
        self::assertNull($event->haltedAt);
        self::assertSame('required_context_missing', $event->haltCode);
    }

    public function test_non_utc_clock_fails_closed(): void
    {
        $this->assertAuditTypesExist();

        $clock = new class implements AuditClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-09 19:20:30.123456', new DateTimeZone('Europe/Istanbul'));
            }
        };

        $this->expectException(AuditConfigurationViolation::class);
        (new AuditEventFactory($clock))->create(ActionPipelineOutcome::completed(
            new ActionPipelineState(
                definition: $this->definition(),
                input: [],
                context: $this->context(),
                hasOutput: true,
                output: null,
            ),
        ));
    }

    private function assertAuditTypesExist(): void
    {
        self::assertTrue(class_exists(AuditEventFactory::class), 'AuditEventFactory must exist.');
        self::assertTrue(interface_exists(AuditClock::class), 'AuditClock must exist.');
        self::assertTrue(enum_exists(AuditOutcomeKind::class), 'AuditOutcomeKind must exist.');
        self::assertTrue(class_exists(AuditConfigurationViolation::class), 'AuditConfigurationViolation must exist.');
    }

    private function factory(): AuditEventFactory
    {
        $clock = new class implements AuditClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-09 16:20:30.123456', new DateTimeZone('UTC'));
            }
        };

        return new AuditEventFactory($clock);
    }

    private function context(): InvocationContext
    {
        return new InvocationContext('webmcp', 'corr-default');
    }

    /**
     * @param array<string, mixed> $inputSchema
     * @param array<string, mixed>|null $outputSchema
     * @param array<string, mixed> $extensions
     */
    private function definition(
        array $inputSchema = ['type' => 'object'],
        ?array $outputSchema = ['type' => 'object'],
        array $extensions = [],
    ): ActionDefinition {
        return new ActionDefinition(
            id: 'audit.test',
            version: 2,
            title: 'Audit test',
            description: 'Audit projection test action.',
            inputSchema: $inputSchema,
            scope: ActionScope::Portable,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RecommendedKey,
            outputSensitivity: OutputSensitivity::Sensitive,
            outputContentTrust: OutputContentTrust::ContainsUntrustedContent,
            contextRequirements: [],
            outputSchema: $outputSchema,
            extensions: $extensions,
        );
    }
}
