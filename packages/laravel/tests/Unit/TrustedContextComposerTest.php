<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputTrust;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineDecision;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStageHandler;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;

final class TrustedContextComposerTest extends TestCase
{
    // ------------------------------------------------------------------
    // Composition
    // ------------------------------------------------------------------

    public function test_composes_actor_and_tenant_entries_in_canonical_order(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver('real-user'),
            new StaticTenantResolver('tenant-A'),
        );

        $entries = $composer->resolve();

        self::assertSame(
            ['authenticated_actor', 'tenant'],
            array_map(static fn (TrustedContextEntry $entry): string => $entry->requirement->value, $entries),
        );
        self::assertSame('real-user', $entries[0]->value);
        self::assertSame('tenant-A', $entries[1]->value);
    }

    public function test_actor_only_composition(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver('real-user'),
            new StaticTenantResolver(null),
        );

        $entries = $composer->resolve();

        self::assertCount(1, $entries);
        self::assertSame(ContextRequirement::AuthenticatedActor, $entries[0]->requirement);
    }

    public function test_tenant_only_composition(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver(null),
            new StaticTenantResolver('tenant-A'),
        );

        $entries = $composer->resolve();

        self::assertCount(1, $entries);
        self::assertSame(ContextRequirement::Tenant, $entries[0]->requirement);
    }

    public function test_neither_resolver_resolves_to_empty_list(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver(null),
            new StaticTenantResolver(null),
        );

        self::assertSame([], $composer->resolve());
    }

    public function test_falsy_legitimate_values_are_not_dropped_by_truthiness(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver(null),
            new StaticTenantResolver(0),
        );

        $entries = $composer->resolve();

        self::assertCount(1, $entries);
        self::assertSame(0, $entries[0]->value, '0 is a legitimate resolved tenant value; null is the only absence.');
    }

    public function test_provenance_is_preserved_verbatim_through_composition(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver('real-user', 'laravel.auth', 'admin'),
            new StaticTenantResolver('tenant-A', 'acme.tenancy', 'tenant-7'),
        );

        $entries = $composer->resolve();

        self::assertSame(['laravel.auth', 'admin'], [$entries[0]->provenance->provider, $entries[0]->provenance->reference]);
        self::assertSame(['acme.tenancy', 'tenant-7'], [$entries[1]->provenance->provider, $entries[1]->provenance->reference]);
    }

    public function test_resolved_trusted_value_rejects_null(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not hold null');
        new ResolvedTrustedValue(null, new ContextProvenance('test.resolver'));
    }

    public function test_composed_entries_feed_invocation_context_naturally(): void
    {
        $composer = new TrustedContextComposer(
            new StaticActorResolver('real-user'),
            new StaticTenantResolver('tenant-A'),
        );
        $context = new InvocationContext('test', 'corr-1', $composer->resolve());

        self::assertTrue($context->has(ContextRequirement::AuthenticatedActor));
        self::assertTrue($context->has(ContextRequirement::Tenant));
        self::assertFalse($context->has(ContextRequirement::CurrentRecord));
    }

    public function test_resolver_contracts_accept_no_arguments(): void
    {
        foreach ([AuthenticatedActorResolver::class, TenantResolver::class] as $contract) {
            $resolve = new \ReflectionMethod($contract, 'resolve');
            self::assertSame(
                [],
                $resolve->getParameters(),
                sprintf('%s::resolve() must accept no arguments (no caller input, no metadata).', $contract),
            );
        }
    }

    // ------------------------------------------------------------------
    // Critical trust-boundary integration (§16)
    // ------------------------------------------------------------------

    public function test_bus_accepts_composed_context_and_halts_when_tenant_unresolvable(): void
    {
        $definition = $this->definition(requirements: [
            ContextRequirement::AuthenticatedActor,
            ContextRequirement::Tenant,
        ]);
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        // 1. Both resolvers resolve → requirement gate passes → stages run.
        $composer = new TrustedContextComposer(
            new StaticActorResolver('real-user'),
            new StaticTenantResolver('tenant-A'),
        );
        $log = [];
        $auditor = new TrustRecordingAuditor($log);
        $bus = new ActionBus($registry, $auditor, $this->busHandlers($log));
        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['reason' => 'damaged'],
            new InvocationContext('test', 'corr-1', $composer->resolve()),
        ));
        self::assertTrue($outcome->completed, 'Composed trusted entries must satisfy the requirement gate.');

        // 2. Tenant resolver returns null while caller input AND metadata both
        //    carry attacker-controlled "tenant" values → gate must halt.
        $attackerComposer = new TrustedContextComposer(
            new StaticActorResolver('real-user'),
            new StaticTenantResolver(null),
        );
        $context = new InvocationContext(
            'test',
            'corr-2',
            $attackerComposer->resolve(),
            metadata: ['tenant' => 'also-attacker-controlled'],
        );

        $log = [];
        $auditor = new TrustRecordingAuditor($log);
        $bus = new ActionBus($registry, $auditor, $this->busHandlers($log));
        $outcome = $bus->dispatch(new ActionCall(
            $definition->id,
            $definition->version,
            ['reason' => 'damaged', 'tenant' => 'attacker-controlled'],
            $context,
        ));

        self::assertSame(['audit'], $log, 'No pipeline stage may run when the trusted tenant is absent.');
        self::assertFalse($outcome->completed);
        self::assertNull($outcome->haltedAt);
        self::assertSame('required_context_missing', $outcome->halt->code);
        self::assertSame(['requirements' => ['tenant']], $outcome->halt->details);
        self::assertSame(1, $auditor->calls);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * @return list<TrustProbeHandler>
     */
    private function busHandlers(array &$log): array
    {
        $handlers = [];
        foreach (ActionPipelineStage::cases() as $stage) {
            $behavior = null;
            if ($stage === ActionPipelineStage::Execution) {
                $behavior = static fn (ActionPipelineState $state) => $state->withOutput('executed');
            }
            $handlers[] = new TrustProbeHandler($stage, $log, $behavior);
        }
        return $handlers;
    }

    /**
     * @param list<ContextRequirement> $requirements
     */
    private function definition(array $requirements): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund.prepare',
            version: 1,
            title: 'Prepare refund',
            description: 'Creates a reviewable refund draft for the current order.',
            inputSchema: ['type' => 'object'],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputTrust: OutputTrust::Sensitive,
            contextRequirements: $requirements,
        );
    }
}

final class StaticActorResolver implements AuthenticatedActorResolver
{
    public function __construct(
        private readonly mixed $value,
        private readonly string $provider = 'test.auth',
        private readonly ?string $reference = null,
    ) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        if ($this->value === null) {
            return null;
        }
        return new ResolvedTrustedValue($this->value, new ContextProvenance($this->provider, $this->reference));
    }
}

final class StaticTenantResolver implements TenantResolver
{
    public function __construct(
        private readonly mixed $value,
        private readonly string $provider = 'test.tenancy',
        private readonly ?string $reference = null,
    ) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        if ($this->value === null) {
            return null;
        }
        return new ResolvedTrustedValue($this->value, new ContextProvenance($this->provider, $this->reference));
    }
}

final class TrustProbeHandler implements ActionPipelineStageHandler
{
    private readonly ActionPipelineStage $stage;

    /** @var list<string> */
    private array $log;

    private readonly ?\Closure $behavior;

    /** @param list<string> $log */
    public function __construct(ActionPipelineStage $stage, array &$log, ?\Closure $behavior = null)
    {
        $this->stage = $stage;
        $this->log = &$log;
        $this->behavior = $behavior;
    }

    public function stage(): ActionPipelineStage
    {
        return $this->stage;
    }

    public function process(ActionPipelineState $state): ActionPipelineDecision
    {
        $this->log[] = $this->stage->value;
        if ($this->behavior !== null) {
            $result = ($this->behavior)($state);
            if ($result instanceof ActionPipelineDecision) {
                return $result;
            }
            return ActionPipelineDecision::continueWith($result);
        }
        return ActionPipelineDecision::continueWith($state);
    }
}

final class TrustRecordingAuditor implements \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineAuditor
{
    public int $calls = 0;

    /** @var list<string> */
    private array $log;

    /** @param list<string> $log */
    public function __construct(array &$log = [])
    {
        $this->log = &$log;
    }

    public function record(\SurfaceRelay\Laravel\Runtime\Pipeline\ActionCall $call, \SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome $outcome): void
    {
        $this->calls++;
        $this->log[] = 'audit';
    }
}
