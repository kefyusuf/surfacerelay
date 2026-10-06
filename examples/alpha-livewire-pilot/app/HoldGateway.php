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
final class HoldGateway
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
            new SystemConfirmationClock(), new RandomConfirmationTokenGenerator(), receiptTtlSeconds: max(5, min(120, (int) (getenv('PILOT_CONFIRMATION_TTL') ?: 60))));
    }

    public static function definition(): ActionDefinition
    {
        return new ActionDefinition('pilot.livewire.orders.hold', 1, 'Hold current order',
            'Place the currently authorized tenant order on hold after human approval.',
            ['type' => 'object', 'properties' => ['reason' => ['type' => 'string']],
                'required' => ['reason'], 'additionalProperties' => false],
            ActionScope::PageScoped, ActionEffect::ReversibleWrite, ActionRisk::Consequential,
            IdempotencyPolicy::RequiredKey, OutputSensitivity::Sensitive, OutputContentTrust::TrustedApplicationData,
            [ContextRequirement::AuthenticatedActor, ContextRequirement::Tenant, ContextRequirement::CurrentRecord,
                ContextRequirement::BrowserSession]);
    }

    public static function invoke(OrderDesk $component, string $reason): array
    {
        $row = self::active($component);
        $input = ['reason' => $reason];
        $payload = ['idempotencyKey' => $row->idempotency_key];
        if ($row->receipt_ciphertext !== null) { $payload['receipt'] = \Illuminate\Support\Facades\Crypt::decryptString($row->receipt_ciphertext); }
        $definition = self::definition();
        $stored = json_decode($row->descriptor, true, flags: JSON_THROW_ON_ERROR);
        $descriptor = new RuntimeBinding($row->binding_id, $definition, 'livewire', BindingLifecycle::Component,
            $stored['target'], gmdate('Y-m-d\\TH:i:s\\Z', $row->expires_at));
        $registry = new InMemoryActionRegistry();
        $registry->register($definition);
        $rules = new InMemoryActionValidationRules();
        $rules->register($definition, ['reason' => ['required', 'string', 'in:customer-request,duplicate-order']]);
        $tenantResolver = new class implements TenantResolver {
            public function resolve(): ?ResolvedTrustedValue
            {
                $tenant = HoldGateway::tenant();
                return $tenant === null ? null : new ResolvedTrustedValue($tenant,
                    new ContextProvenance('acceptance.membership'), confirmationScopeKey: $tenant);
            }
        };
        $entries = (new TrustedContextComposer(new LaravelAuthenticatedActorResolver(app('auth'), 'web'), $tenantResolver))->resolve();
        foreach ([ContextRequirement::CurrentRecord->value => $row->record_id,
            ContextRequirement::BrowserSession->value => session()->getId()] as $requirement => $value) {
            if ($value !== null) { $entries[] = new TrustedContextEntry(ContextRequirement::from($requirement), $value,
                new ContextProvenance('acceptance.session')); }
        }
        $context = new InvocationContext('livewire', bin2hex(random_bytes(16)), $entries,
            $payload['idempotencyKey'] ?? null, is_array($payload['metadata'] ?? null) ? $payload['metadata'] : []);
        Gate::define('pilot.hold', static function (User $user, InvocationContext $context): bool {
            $tenant = $context->require(ContextRequirement::Tenant)->value;
            if (!DB::table('memberships')->where('user_id', $user->id)->where('tenant_id', $tenant)->where('can_hold', true)->exists()) { return false; }
            $id = $context->require(ContextRequirement::CurrentRecord)->value;
            return DB::table('orders')->where('tenant_id', $tenant)->where('id', $id)->exists();
        });
        $authorization = new InMemoryActionAuthorizationRules();
        $authorization->register($definition, new LaravelAuthorizationRule('pilot.hold', static fn ($input, $context) => [$context]));
        $idempotency = new IdempotencyService(new DatabaseIdempotencyStore(DB::connection()),
            new class implements IdempotencyClock { public function now(): int { return time(); } }, new IdempotencyReplayCodec());
        $executor = new class implements ActionExecutor {
            public function execute(ActionDefinition $definition, array $input, InvocationContext $context): mixed
            {
                $ids = [$context->require(ContextRequirement::CurrentRecord)->value];
                DB::table('effects')->insert(['actor_id' => $context->require(ContextRequirement::AuthenticatedActor)->value->id,
                    'tenant_id' => $context->require(ContextRequirement::Tenant)->value, 'order_ids' => json_encode($ids, JSON_THROW_ON_ERROR),
                    'reason' => $input['reason'], 'worker_pid' => getmypid()]);
                return ['orderId' => $ids[0], 'held' => true, 'secret' => 'HOLD_RAW_OUTPUT_SECRET'];
            }
        };
        $redactor = new class implements SensitiveOutputRedactor {
            public function redact(ActionDefinition $definition, mixed $rawOutput, OutputPolicyContext $context): OutputRedactionResult
            {
                return OutputRedactionResult::release(['orderId' => $rawOutput['orderId'], 'held' => $rawOutput['held']]);
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
            DB::table('pilot_livewire_bindings')->where('binding_id', $row->binding_id)->update([
                'challenge_id' => $outcome->halt->confirmation->challengeId,
                'pending_reason' => $reason, 'receipt_ciphertext' => null]);
        } elseif ($outcome->completed) {
            DB::table('pilot_livewire_bindings')->where('binding_id', $row->binding_id)
                ->update(['receipt_ciphertext' => null, 'challenge_id' => null]);
        }
        return (new ActionResultNormalizer())->normalize($outcome)->toArray();
    }

    public static function active(OrderDesk $component): object
    {
        $row = DB::table('pilot_livewire_bindings')->where('binding_id', $component->bindingId)->first();
        abort_unless($row !== null && $row->active && $row->component_id === $component->getId()
            && session('active_binding') === $row->binding_id && $row->expires_at > time()
            && $row->session_hash === hash('sha256', session()->getId()) && $row->actor_id === Auth::id()
            && $row->tenant_id === self::tenant() && $row->record_id === $component->recordId
            && $row->record_id === session('record', 101), 409);
        return $row;
    }
}
