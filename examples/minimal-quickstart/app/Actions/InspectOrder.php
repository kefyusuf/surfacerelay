<?php

declare(strict_types=1);

namespace App\Actions;

use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use SurfaceRelay\Laravel\Audit\AuditEventFactory;
use SurfaceRelay\Laravel\Audit\DatabaseAuditEventStore;
use SurfaceRelay\Laravel\Audit\StructuredActionPipelineAuditor;
use SurfaceRelay\Laravel\Audit\SystemAuditClock;
use SurfaceRelay\Laravel\Auth\LaravelAuthenticatedActorResolver;
use SurfaceRelay\Laravel\Authorization\InMemoryActionAuthorizationRules;
use SurfaceRelay\Laravel\Authorization\LaravelAuthorizationRule;
use SurfaceRelay\Laravel\Authorization\LaravelGateActionAuthorizer;
use SurfaceRelay\Laravel\Confirmation\CacheConfirmationStore;
use SurfaceRelay\Laravel\Confirmation\ConfirmationScopeHasher;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Confirmation\ConfirmationStage;
use SurfaceRelay\Laravel\Confirmation\RandomConfirmationTokenGenerator;
use SurfaceRelay\Laravel\Confirmation\SystemConfirmationClock;
use SurfaceRelay\Laravel\Contracts\ActionExecutor;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Invocation\FilamentActionGateway;
use SurfaceRelay\Laravel\Idempotency\DatabaseIdempotencyStore;
use SurfaceRelay\Laravel\Idempotency\IdempotencyClock;
use SurfaceRelay\Laravel\Idempotency\IdempotencyIntentHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyHasher;
use SurfaceRelay\Laravel\Idempotency\IdempotencyKeyValidator;
use SurfaceRelay\Laravel\Idempotency\IdempotencyReplayCodec;
use SurfaceRelay\Laravel\Idempotency\IdempotencyService;
use SurfaceRelay\Laravel\Idempotency\IdempotencyStage;
use SurfaceRelay\Laravel\OutputPolicy\OutputPolicyStage;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionBus;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionExecutionStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\AuthorizationStage;
use App\Support\FixedTenantResolver;
use SurfaceRelay\Laravel\Validation\InMemoryActionValidationRules;
use SurfaceRelay\Laravel\Validation\LaravelInputValidationStage;

/**
 * Quickstart step 1: one read-only Action Definition plus its executor.
 * Caller input is untrusted; the tenant comes from the trusted resolver and
 * the Gate re-checks the order inside that tenant on every dispatch.
 */
final class InspectOrder
{
    public static function definition(): ActionDefinition
    {
        return new ActionDefinition(
            id: 'quickstart.orders.inspect',
            version: 1,
            title: 'Inspect order',
            description: 'Read the status of one order belonging to the tenant.',
            inputSchema: [
                'type' => 'object',
                'properties' => ['order_id' => ['type' => 'integer', 'minimum' => 1]],
                'required' => ['order_id'],
                'additionalProperties' => false,
            ],
            scope: ActionScope::PageScoped,
            effect: ActionEffect::Read,
            risk: ActionRisk::Low,
            idempotency: IdempotencyPolicy::None,
            outputSensitivity: OutputSensitivity::Normal,
            outputContentTrust: OutputContentTrust::TrustedApplicationData,
            contextRequirements: [ContextRequirement::AuthenticatedActor, ContextRequirement::Tenant],
        );
    }

    public function dispatch(Page $page, array $untrustedInput): ActionPipelineOutcome
    {
        $definition = self::definition();

        $registry = new InMemoryActionRegistry();
        $registry->register($definition);

        $validation = new InMemoryActionValidationRules();
        $validation->register($definition, ['order_id' => ['required', 'integer', 'min:1']]);

        $authorization = new InMemoryActionAuthorizationRules();
        $authorization->register($definition, new LaravelAuthorizationRule(
            ability: 'view-quickstart-order',
            arguments: static fn (array $input, InvocationContext $context): array => [
                $context->require(ContextRequirement::Tenant)->value,
                (int) $input['order_id'],
            ],
        ));

        $idempotency = new IdempotencyService(
            new DatabaseIdempotencyStore(DB::connection()),
            new class implements IdempotencyClock {
                public function now(): int { return time(); }
            },
            new IdempotencyReplayCodec(),
        );

        $bus = new ActionBus(
            $registry,
            new StructuredActionPipelineAuditor(
                new AuditEventFactory(new SystemAuditClock()),
                new DatabaseAuditEventStore(DB::connection()),
            ),
            [
                new LaravelInputValidationStage(app(ValidatorFactory::class), $validation),
                new AuthorizationStage(new LaravelGateActionAuthorizer(app(Gate::class), $authorization)),
                new IdempotencyStage(new IdempotencyKeyValidator(), new IdempotencyKeyHasher(), new IdempotencyIntentHasher(), $idempotency),
                new ConfirmationStage(
                    new ConfirmationService(
                        new CacheConfirmationStore(Cache::store('file')->getStore()),
                        new SystemConfirmationClock(),
                        new RandomConfirmationTokenGenerator(),
                    ),
                    new ConfirmationScopeHasher(),
                ),
                new ActionExecutionStage(
                    new class implements ActionExecutor {
                        public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
                        {
                            $tenant = $context->require(ContextRequirement::Tenant)->value;
                            // Never query an order by caller-supplied ID outside the tenant.
                            $row = DB::table('quickstart_orders')
                                ->where('id', $input['order_id'])
                                ->where('tenant_id', $tenant)
                                ->first(['id', 'status']);
                            if ($row === null) {
                                throw new \LogicException('Quickstart order disappeared after authorization.');
                            }
                            return ['orderId' => (int) $row->id, 'status' => $row->status];
                        }
                    },
                    $idempotency,
                ),
                new OutputPolicyStage(),
            ],
        );

        return (new FilamentActionGateway(
            $bus,
            new TrustedContextComposer(
                new LaravelAuthenticatedActorResolver(app(AuthFactory::class), 'web'),
                new FixedTenantResolver(),
            ),
        ))->dispatch(
            page: $page,
            actionId: $definition->id,
            actionVersion: $definition->version,
            input: $untrustedInput,
            surface: 'filament',
            correlationId: bin2hex(random_bytes(16)),
        );
    }
}
