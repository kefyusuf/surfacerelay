<?php

declare(strict_types=1);

namespace App;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\{Auth, Cache, Crypt, DB};
use SurfaceRelay\Laravel\Binding\RandomBindingIdGenerator;
use SurfaceRelay\Laravel\Livewire\Attributes\ExposeAction;
use SurfaceRelay\Laravel\Livewire\Binding\LivewireBindingProducer;
use SurfaceRelay\Laravel\Livewire\Exposure\LivewireActionExposureReader;
use SurfaceRelay\Laravel\Livewire\Identity\MethodLivewireComponentIdentityResolver;
use SurfaceRelay\Laravel\Registry\InMemoryActionRegistry;

/** Explicit mounted consumer; public snapshots never carry confirmation receipts. */
final class OrderDesk extends Component
{
    #[Locked]
    public int $recordId = 101;

    #[Locked]
    public string $bindingId = '';

    #[Locked]
    public array $result = [];

    public function mount(): void
    {
        $this->recordId = session('record', 101);
        $registry = new InMemoryActionRegistry(); $registry->register(HoldGateway::definition());
        $binding = (new LivewireBindingProducer(new LivewireActionExposureReader($registry),
            new MethodLivewireComponentIdentityResolver(), new RandomBindingIdGenerator()))->forComponent($this)[0];
        $descriptor = $binding->toArray(); $descriptor['expiresAt'] = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
        $this->bindingId = $binding->bindingId;
        $old = session('active_binding');
        if (is_string($old)) DB::table('pilot_livewire_bindings')->where('binding_id', $old)
            ->update(['active' => false, 'receipt_ciphertext' => null]);
        DB::table('pilot_livewire_bindings')->insert(['binding_id' => $this->bindingId,
            'component_id' => $this->getId(), 'session_hash' => hash('sha256', session()->getId()),
            'actor_id' => Auth::id(), 'tenant_id' => HoldGateway::tenant(), 'record_id' => $this->recordId,
            'active' => true, 'descriptor' => json_encode($descriptor, JSON_THROW_ON_ERROR),
            'expires_at' => time() + 3600, 'idempotency_key' => 'hold-' . bin2hex(random_bytes(16))]);
        session()->put('active_binding', $this->bindingId);
    }

    public function hydrate(): void
    {
        HoldGateway::active($this);
    }

    #[ExposeAction('pilot.livewire.orders.hold', 1)]
    public function holdOrder(mixed $reason): array
    {
        abort_unless(func_num_args() === 1 && is_string($reason), 422);
        return Cache::store('file')->lock('livewire-hold-' . $this->bindingId, 15)->block(5, function () use ($reason) {
            return $this->result = HoldGateway::invoke($this, $reason);
        });
    }

    public function approveHold(): array
    {
        abort_unless(func_num_args() === 0, 422);
        return Cache::store('file')->lock('livewire-hold-' . $this->bindingId, 15)->block(5, function () {
            $row = HoldGateway::active($this);
            $displayed = $this->result['confirmation']['challengeId'] ?? null;
            abort_unless(is_string($displayed) && $displayed === $row->challenge_id, 409);
            abort_unless(Auth::check() && DB::table('memberships')->where('user_id', Auth::id())
                ->where('tenant_id', $row->tenant_id)->where('can_hold', true)->exists()
                && is_string($row->challenge_id) && $row->receipt_ciphertext === null, 403);
            $receipt = HoldGateway::confirmation()->approveChallenge($row->challenge_id);
            abort_unless($receipt !== null, 409);
            DB::table('pilot_livewire_bindings')->where('binding_id', $this->bindingId)
                ->update(['receipt_ciphertext' => Crypt::encryptString($receipt)]);
            return ['status' => 'approved'];
        });
    }

    public function selectOrder(int $id): array
    {
        return Cache::store('file')->lock('livewire-hold-' . $this->bindingId, 15)->block(5, function () use ($id) {
            $row = HoldGateway::active($this);
            abort_unless(Auth::check() && DB::table('orders')->where('id', $id)->where('tenant_id', $row->tenant_id)->exists(), 403);
            DB::table('pilot_livewire_bindings')->where('binding_id', $this->bindingId)->update([
                'record_id' => $id, 'idempotency_key' => 'hold-' . bin2hex(random_bytes(16)),
                'challenge_id' => null, 'pending_reason' => null, 'receipt_ciphertext' => null]);
            $this->recordId = $id; $this->result = []; session()->put('record', $id);
            return ['orderId' => $id];
        });
    }

    public function togglePermission(): array
    {
        $row = HoldGateway::active($this);
        abort_unless(Auth::id() === 1, 403);
        $membership = DB::table('memberships')->where('user_id', Auth::id())->where('tenant_id', $row->tenant_id);
        $allowed = !$membership->value('can_hold'); $membership->update(['can_hold' => $allowed]);
        return ['canHold' => $allowed];
    }

    public function render()
    {
        $row = HoldGateway::active($this);
        $allowed = Auth::check() && DB::table('memberships')->where('user_id', Auth::id())
            ->where('tenant_id', $row->tenant_id)->where('can_hold', true)->exists();
        $definition = array_map(static fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            get_object_vars(HoldGateway::definition()));
        $definition['contextRequirements'] = array_map(static fn ($requirement) => $requirement->value, $definition['contextRequirements']);
        return view('order-desk', ['binding' => json_decode($row->descriptor, true, flags: JSON_THROW_ON_ERROR),
            'definition' => $definition, 'allowed' => $allowed, 'tenant' => $row->tenant_id,
            'pendingReason' => $row->pending_reason, 'pending' => $row->challenge_id !== null,
            'approved' => $row->receipt_ciphertext !== null,
            'orders' => DB::table('orders')->where('tenant_id', $row->tenant_id)->orderBy('id')->get()]);
    }
}
