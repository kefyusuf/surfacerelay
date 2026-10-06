<?php

declare(strict_types=1);

namespace App;

use Illuminate\Support\Facades\{Auth, Cache, DB, Gate};
use SurfaceRelay\Laravel\Auth\LaravelAuthenticatedActorResolver;
use SurfaceRelay\Laravel\Binding\{BindingLifecycle, RuntimeBinding};
use SurfaceRelay\Laravel\Authorization\{InMemoryActionAuthorizationRules, LaravelAuthorizationRule, LaravelGateActionAuthorizer};
use SurfaceRelay\Laravel\Audit\{AuditEventFactory, DatabaseAuditEventStore, StructuredActionPipelineAuditor, SystemAuditClock};
use SurfaceRelay\Laravel\Confirmation\{CacheConfirmationStore, ConfirmationScopeHasher, ConfirmationService, ConfirmationStage, RandomConfirmationTokenGenerator, SystemConfirmationClock};
use SurfaceRelay\Laravel\Contracts\{ActionExecutor, TenantResolver};
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\{ActionEffect, ActionRisk, ActionScope, ContextRequirement, IdempotencyPolicy, OutputContentTrust, OutputSensitivity};
use SurfaceRelay\Laravel\Idempotency\{DatabaseIdempotencyStore, IdempotencyClock, IdempotencyIntentHasher, IdempotencyKeyHasher, IdempotencyKeyValidator, IdempotencyReplayCodec, IdempotencyService, IdempotencyStage};
use SurfaceRelay\Laravel\OutputPolicy\{OutputPolicyContext, OutputPolicyStage, OutputRedactionResult, SensitiveOutputRedactor};
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;
use SurfaceRelay\Laravel\Result\ActionResultNormalizer;
use SurfaceRelay\Laravel\Runtime\Context\{ContextProvenance, ResolvedTrustedValue, TrustedContextComposer, TrustedContextEntry};
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\{ActionBus, ActionCall, ActionExecutionStage, AuthorizationStage};
use SurfaceRelay\Laravel\Validation\{InMemoryActionValidationRules, LaravelInputValidationStage};

/** Test application wiring; no pass-through policy stages. */
final class AcceptanceRuntime
{
    public static function tenant(): ?string
    {
        $tenant = session('tenant');
        return Auth::check() && is_string($tenant) && DB::table('memberships')
            ->where('user_id', Auth::id())->where('tenant_id', $tenant)->exists() ? $tenant : null;
    }

    public static function approvalOwner(): string
    {
        return hash('sha256', json_encode([Auth::id(), self::tenant(), session()->getId()], JSON_THROW_ON_ERROR));
    }

    public static function confirmation(): ConfirmationService
    {
        return new ConfirmationService(new CacheConfirmationStore(Cache::store('file')->getStore()),
            new SystemConfirmationClock(), new RandomConfirmationTokenGenerator(), receiptTtlSeconds: 5);
    }

    public static function invoke(array $payload): array
    {
        $binding = session('binding');
        $candidate = $payload['bindingId'] ?? ($binding['id'] ?? null);
        abort_unless(is_array($binding) && is_string($candidate) && $candidate === $binding['id']
            && $binding['driver'] === 'acceptance.http' && $binding['session'] === session()->getId()
            && $binding['expires'] > time(), 409);
        $input = $payload['input'] ?? [];
        abort_unless(is_array($input) && array_diff(array_keys($input), ['reason']) === [], 422);
        abort_unless(!isset($payload['receipt']) || is_string($payload['receipt']), 422);
        abort_unless(!isset($payload['idempotencyKey']) || is_string($payload['idempotencyKey']), 422);
        $definition = new ActionDefinition('acceptance.orders.refund', 1, 'Refund selected orders',
            'Refund the current authorized selection.',
            ['type' => 'object', 'properties' => ['reason' => ['type' => 'string']], 'required' => ['reason'], 'additionalProperties' => false],
            ActionScope::PageScoped, ActionEffect::ExternalSideEffect, ActionRisk::Consequential,
            IdempotencyPolicy::RequiredKey, OutputSensitivity::Sensitive, OutputContentTrust::TrustedApplicationData,
            [ContextRequirement::AuthenticatedActor, ContextRequirement::Tenant, ContextRequirement::CurrentRecord,
                ContextRequirement::CurrentSelection, ContextRequirement::BrowserSession]);
        $descriptor = new RuntimeBinding($binding['id'], $definition, $binding['driver'],
            BindingLifecycle::Session, ['endpoint' => '/invoke'], gmdate('Y-m-d\TH:i:s\Z', $binding['expires']));
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $rules = new InMemoryActionValidationRules();
        $rules->register($definition, ['reason' => ['required', 'string', 'in:customer-request,duplicate-order']]);
        $tenantResolver = new class implements TenantResolver {
            public function resolve(): ?ResolvedTrustedValue
            {
                $tenant = AcceptanceRuntime::tenant();
                return $tenant === null ? null : new ResolvedTrustedValue($tenant,
                    new ContextProvenance('acceptance.membership'), confirmationScopeKey: $tenant);
            }
        };
        $entries = (new TrustedContextComposer(new LaravelAuthenticatedActorResolver(app('auth'), 'web'), $tenantResolver))->resolve();
        foreach ([ContextRequirement::CurrentRecord->value => session('record'),
            ContextRequirement::CurrentSelection->value => session('selection', []),
            ContextRequirement::BrowserSession->value => session()->getId()] as $requirement => $value) {
            if ($value !== null) { $entries[] = new TrustedContextEntry(ContextRequirement::from($requirement), $value,
                new ContextProvenance('acceptance.session')); }
        }
        $context = new InvocationContext('acceptance.http', bin2hex(random_bytes(16)), $entries,
            $payload['idempotencyKey'] ?? null, is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []);
        Gate::define('acceptance.refund', static function (User $user, InvocationContext $context): bool {
            $tenant = $context->require(ContextRequirement::Tenant)->value;
            if (!DB::table('memberships')->where('user_id', $user->id)->where('tenant_id', $tenant)->where('can_refund', true)->exists()) { return false; }
            $ids = array_unique([$context->require(ContextRequirement::CurrentRecord)->value,
                ...$context->require(ContextRequirement::CurrentSelection)->value]);
            return $ids !== [] && DB::table('orders')->where('tenant_id', $tenant)->whereIn('id', $ids)->count() === count($ids);
        });
        $authorization = new InMemoryActionAuthorizationRules();
        $authorization->register($definition, new LaravelAuthorizationRule('acceptance.refund', static fn ($input, $context) => [$context]));
        $idempotency = new IdempotencyService(new DatabaseIdempotencyStore(DB::connection()),
            new class implements IdempotencyClock { public function now(): int { return time(); } }, new IdempotencyReplayCodec());
        $executor = new class implements ActionExecutor {
            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                usleep(800000);
                $ids = $context->require(ContextRequirement::CurrentSelection)->value;
                DB::table('effects')->insert(['actor_id' => $context->require(ContextRequirement::AuthenticatedActor)->value->id,
                    'tenant_id' => $context->require(ContextRequirement::Tenant)->value, 'order_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
                    'reason' => $input['reason'], 'worker_pid' => getmypid()]);
                return ['orderIds' => $ids, 'refundedCount' => count($ids), 'secret' => 'ACCEPTANCE_RAW_OUTPUT_SECRET'];
            }
        };
        $redactor = new class implements SensitiveOutputRedactor {
            public function redact(ActionDefinition $definition, mixed $rawOutput, OutputPolicyContext $context): OutputRedactionResult
            {
                return OutputRedactionResult::release(['orderIds' => $rawOutput['orderIds'], 'refundedCount' => $rawOutput['refundedCount']]);
            }
        };
        $bus = new ActionBus($registry, new StructuredActionPipelineAuditor(new AuditEventFactory(new SystemAuditClock()),
            new DatabaseAuditEventStore(DB::connection())), [
                new LaravelInputValidationStage(app('validator'), $rules),
                new AuthorizationStage(new LaravelGateActionAuthorizer(app(\Illuminate\Contracts\Auth\Access\Gate::class), $authorization)),
                new IdempotencyStage(new IdempotencyKeyValidator(), new IdempotencyKeyHasher(), new IdempotencyIntentHasher(), $idempotency),
                new ConfirmationStage(self::confirmation(), new ConfirmationScopeHasher()),
                new ActionExecutionStage($executor, $idempotency), new OutputPolicyStage($redactor),
            ]);
        $outcome = $bus->dispatch(new ActionCall($definition->id, 1, $input, $context, $descriptor->bindingId, $payload['receipt'] ?? null));
        if ($outcome->halt?->confirmation !== null) {
            session()->put('challenges.' . $outcome->halt->confirmation->challengeId, self::approvalOwner());
        }
        return (new ActionResultNormalizer())->normalize($outcome)->toArray();
    }
}
