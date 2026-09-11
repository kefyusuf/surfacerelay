<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Support;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Foundation\Application;
use RuntimeException;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\DatabaseAuditEventStore;
use SurfaceRelay\Laravel\Audit\StructuredActionPipelineAuditor;
use SurfaceRelay\Laravel\Audit\SystemAuditClock;
use SurfaceRelay\Laravel\Authorization\InMemoryActionAuthorizationRules;
use SurfaceRelay\Laravel\Authorization\LaravelAuthorizationRule;
use SurfaceRelay\Laravel\Authorization\LaravelGateActionAuthorizer;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Context\FilamentContextExposure;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\EditOrder;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\ListOrders;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\Order;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoActorContext;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoActorResolver;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoExecutor;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoTenantContext;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\OrderDemo\OrderDemoTenantResolver;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

final class FilamentOrderDemoHarness
{
    public readonly OrderDemoActorContext $actor;

    public readonly OrderDemoTenantContext $tenant;

    public readonly OrderDemoExecutor $executor;

    public readonly ConfirmationService $confirmationService;

    public readonly IdempotencyService $idempotencyService;

    public readonly FilamentActionGateway $gateway;

    private int $correlationSequence = 0;

    public function __construct(
        Application $app,
        string $actorTenant,
        string $activeTenant,
    ) {
        $this->actor = new OrderDemoActorContext();
        $this->actor->set(new GenericUser([
            'id' => 'actor-' . $actorTenant,
            'tenant_id' => $actorTenant,
            'can_hold' => true,
            'can_refund' => true,
        ]));

        $this->tenant = new OrderDemoTenantContext();
        $this->tenant->set($activeTenant);

        $app->instance(OrderDemoActorContext::class, $this->actor);
        $app->instance(OrderDemoTenantContext::class, $this->tenant);

        $registry = new InMemoryActionRegistry();
        $holdDefinition = $this->holdDefinition();
        $refundDefinition = $this->refundDefinition();
        $registry->register($holdDefinition);
        $registry->register($refundDefinition);

        $validationRules = new InMemoryActionValidationRules();
        $validationRules->register($holdDefinition, [
            'reason' => ['required', 'string', 'min:1'],
        ]);
        $validationRules->register($refundDefinition, [
            'reason' => ['required', 'string', 'min:1'],
        ]);

        $authorizationRules = new InMemoryActionAuthorizationRules();
        $authorizationRules->register($holdDefinition, new LaravelAuthorizationRule(
            ability: 'order-demo.hold',
            arguments: static fn (array $input, InvocationContext $context): array => [
                $context->require(ContextRequirement::CurrentRecord)->value,
                $context->require(ContextRequirement::Tenant)->value,
            ],
        ));
        $authorizationRules->register($refundDefinition, new LaravelAuthorizationRule(
            ability: 'order-demo.refund-selected',
            arguments: static fn (array $input, InvocationContext $context): array => [
                $context->require(ContextRequirement::CurrentSelection)->value,
                $context->require(ContextRequirement::Tenant)->value,
            ],
        ));

        /** @var Gate $gate */
        $gate = $app->make(Gate::class);
        $gate->define(
            'order-demo.hold',
            static function (GenericUser $actor, Order $order, string $tenantId): bool {
                return $actor->getAuthIdentifier() !== null
                    && ($actor->tenant_id ?? null) === $tenantId
                    && $order->tenant_id === $tenantId
                    && ($actor->can_hold ?? false) === true;
            },
        );
        $gate->define(
            'order-demo.refund-selected',
            static function (GenericUser $actor, array $orders, string $tenantId): bool {
                if (
                    $actor->getAuthIdentifier() === null
                    || ($actor->tenant_id ?? null) !== $tenantId
                    || ($actor->can_refund ?? false) !== true
                    || $orders === []
                ) {
                    return false;
                }

                foreach ($orders as $order) {
                    if (!$order instanceof Order || $order->tenant_id !== $tenantId) {
                        return false;
                    }
                }

                return true;
            },
        );

        $this->confirmationService = new ConfirmationService(
            new FilamentConfirmationMemoryStore(),
            new FilamentConfirmationMutableClock(),
            new FilamentConfirmationSequenceTokenGenerator([
                str_repeat('H', 43),
                str_repeat('I', 43),
                str_repeat('J', 43),
                str_repeat('K', 43),
                str_repeat('L', 43),
                str_repeat('M', 43),
                str_repeat('N', 43),
                str_repeat('O', 43),
                str_repeat('P', 43),
            ]),
        );
        $app->instance(ConfirmationService::class, $this->confirmationService);

        $this->idempotencyService = new IdempotencyService(
            new FilamentConfirmationIdempotencyStore(),
            new FilamentConfirmationIdempotencyClock(),
            new IdempotencyReplayCodec(),
        );

        $this->executor = new OrderDemoExecutor();

        $bus = new ActionBus(
            registry: $registry,
            auditor: new StructuredActionPipelineAuditor(
                new AuditEventFactory(new SystemAuditClock()),
                new DatabaseAuditEventStore($app['db']->connection()),
            ),
            handlers: [
                new LaravelInputValidationStage(
                    $app->make(ValidationFactory::class),
                    $validationRules,
                ),
                new AuthorizationStage(new LaravelGateActionAuthorizer(
                    $gate,
                    $authorizationRules,
                )),
                new IdempotencyStage(
                    new IdempotencyKeyValidator(),
                    new IdempotencyKeyHasher(),
                    new IdempotencyIntentHasher(),
                    $this->idempotencyService,
                ),
                new ConfirmationStage(
                    $this->confirmationService,
                    new ConfirmationScopeHasher(),
                ),
                new ActionExecutionStage(
                    $this->executor,
                    $this->idempotencyService,
                ),
                new OutputPolicyStage(),
            ],
        );

        $this->gateway = new FilamentActionGateway(
            bus: $bus,
            baseComposer: new TrustedContextComposer(
                new OrderDemoActorResolver($this->actor),
                new OrderDemoTenantResolver($this->tenant),
            ),
        );
    }

    public function simulateUnscopedHostQuery(): void
    {
        $this->tenant->simulateUnscopedHostQuery();
    }

    public function switchTrustedTenant(string $tenantId): void
    {
        $actorId = (string) $this->actor->current()->getAuthIdentifier();

        $this->tenant->set($tenantId);
        $this->actor->set(new GenericUser([
            'id' => $actorId,
            'tenant_id' => $tenantId,
            'can_hold' => true,
            'can_refund' => true,
        ]));
    }

    public function approve(ConfirmationChallenge $challenge): string
    {
        $receipt = $this->confirmationService->approveChallenge($challenge->challengeId);

        if ($receipt === null) {
            throw new RuntimeException('Order demo confirmation could not be approved.');
        }

        return $receipt;
    }

    /** @param array<string, mixed> $metadata */
    public function dispatchHold(
        EditOrder $page,
        string $reason,
        array $metadata = [],
    ): ActionPipelineOutcome {
        return $this->gateway->dispatch(
            page: $page,
            actionId: 'orders.hold_current',
            actionVersion: 1,
            input: ['reason' => $reason],
            surface: 'filament',
            correlationId: $this->nextCorrelationId('hold'),
            bindingId: 'order-demo-hold-binding',
            metadata: $metadata,
        );
    }

    /** @param array<string, mixed> $metadata */
    public function dispatchRefund(
        ListOrders $page,
        string $reason,
        string $idempotencyKey,
        ?string $confirmationReceipt = null,
        array $metadata = [],
    ): ActionPipelineOutcome {
        return $this->gateway->dispatch(
            page: $page,
            actionId: 'orders.refund_selected',
            actionVersion: 1,
            input: ['reason' => $reason],
            surface: 'filament',
            correlationId: $this->nextCorrelationId('refund'),
            bindingId: 'order-demo-refund-binding',
            confirmationReceipt: $confirmationReceipt,
            idempotencyKey: $idempotencyKey,
            metadata: $metadata,
            contextExposure: FilamentContextExposure::activeFilters(),
        );
    }

    private function nextCorrelationId(string $operation): string
    {
        $this->correlationSequence++;

        return 'order-demo-' . $operation . '-' . $this->correlationSequence;
    }

    private function holdDefinition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.hold_current',
            version: 1,
            title: 'Hold current order',
            description: 'Place the exact trusted current order on hold.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'minLength' => 1],
                ],
                'required' => ['reason'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ReversibleWrite,
            risk: ActionRisk::Moderate,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentRecord,
            ],
        );
    }

    private function refundDefinition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'orders.refund_selected',
            version: 1,
            title: 'Refund selected orders',
            description: 'Refund the exact trusted Filament current selection.',
            inputSchema: [
                'type' => 'object',
                'properties' => [
                    'reason' => ['type' => 'string', 'minLength' => 1],
                ],
                'required' => ['reason'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::ExternalSideEffect,
            risk: ActionRisk::Consequential,
            idempotency: IdempotencyPolicy::RequiredKey,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [
                ContextRequirement::AuthenticatedActor,
                ContextRequirement::Tenant,
                ContextRequirement::CurrentSelection,
                ContextRequirement::HumanConfirmation,
            ],
        );
    }
}
