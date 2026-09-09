<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Audit\AuditClock;
use SurfaceRelay\Laravel\Audit\AuditEvent;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\AuditEventStore;
use SurfaceRelay\Laravel\Audit\StructuredActionPipelineAuditor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class StructuredActionPipelineAuditorTest extends TestCase
{
    public function test_record_appends_exactly_one_semantic_event(): void
    {
        $this->assertTypesExist();
        $store = new class implements AuditEventStore {
            /** @var list<AuditEvent> */
            public array $events = [];

            public function append(AuditEvent $event): void
            {
                $this->events[] = $event;
            }
        };
        $auditor = new StructuredActionPipelineAuditor($this->factory(), $store);
        $outcome = ActionPipelineOutcome::completed($this->state(output: 'SAFE_OUTPUT'));

        $result = $auditor->record($this->call(), $outcome);

        self::assertNull($result);
        self::assertCount(1, $store->events);
        self::assertSame('completed', $store->events[0]->outcomeKind->value);
        self::assertSame('corr-auditor', $store->events[0]->correlationId);
    }

    public function test_halted_record_appends_once_without_mutating_outcome(): void
    {
        $this->assertTypesExist();
        $store = new class implements AuditEventStore {
            /** @var list<AuditEvent> */
            public array $events = [];

            public function append(AuditEvent $event): void
            {
                $this->events[] = $event;
            }
        };
        $state = $this->state();
        $outcome = ActionPipelineOutcome::halted(
            $state,
            ActionPipelineStage::Authorization,
            new ActionPipelineHalt('authorization_denied'),
        );

        (new StructuredActionPipelineAuditor($this->factory(), $store))->record($this->call(), $outcome);

        self::assertCount(1, $store->events);
        self::assertSame('halted', $store->events[0]->outcomeKind->value);
        self::assertSame('authorization_denied', $store->events[0]->haltCode);
        self::assertSame($state, $outcome->state);
        self::assertSame(ActionPipelineStage::Authorization, $outcome->haltedAt);
        self::assertSame('authorization_denied', $outcome->halt?->code);
    }

    public function test_store_failure_propagates_unchanged(): void
    {
        $this->assertTypesExist();
        $failure = new \RuntimeException('test-store-failure');
        $store = new class($failure) implements AuditEventStore {
            public function __construct(private readonly \RuntimeException $failure) {}

            public function append(AuditEvent $event): void
            {
                throw $this->failure;
            }
        };
        $auditor = new StructuredActionPipelineAuditor($this->factory(), $store);

        try {
            $auditor->record($this->call(), ActionPipelineOutcome::completed($this->state(output: null)));
            self::fail('Store failure must propagate.');
        } catch (\RuntimeException $caught) {
            self::assertSame($failure, $caught);
        }
    }

    private function assertTypesExist(): void
    {
        self::assertTrue(interface_exists(AuditEventStore::class), 'AuditEventStore must exist.');
        self::assertTrue(class_exists(StructuredActionPipelineAuditor::class), 'StructuredActionPipelineAuditor must exist.');
    }

    private function factory(): AuditEventFactory
    {
        $clock = new class implements AuditClock {
            public function now(): DateTimeImmutable
            {
                return new DateTimeImmutable('2026-09-09 17:30:00.123456', new DateTimeZone('UTC'));
            }
        };

        return new AuditEventFactory($clock);
    }

    private function call(): ActionCall
    {
        return new ActionCall(
            'audit.test',
            1,
            ['secret' => 'CALLER_INPUT_MUST_NOT_BE_NEEDED_BY_FACTORY'],
            new InvocationContext('webmcp', 'corr-auditor'),
            bindingId: 'CALLER_BINDING_MUST_NOT_BE_NEEDED_BY_FACTORY',
            confirmationReceipt: 'CALLER_RECEIPT_MUST_NOT_BE_NEEDED_BY_FACTORY',
        );
    }

    private function state(mixed $output = null): ActionPipelineState
    {
        return new ActionPipelineState(
            definition: new ActionDefinition(
                id: 'audit.test',
                version: 1,
                title: 'Audit test',
                description: 'Structured auditor test action.',
                inputSchema: ['type' => 'object'],
                scope: ActionScope::Portable,
                effect: ActionEffect::Read,
                risk: ActionRisk::Low,
                idempotency: IdempotencyPolicy::None,
                outputSensitivity: OutputSensitivity::Normal,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [],
            ),
            input: [],
            context: new InvocationContext('webmcp', 'corr-auditor'),
            hasOutput: true,
            output: $output,
        );
    }
}
