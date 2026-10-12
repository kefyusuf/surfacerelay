<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\A2\InspectOrder;
use App\Filament\Resources\A2Orders\A2OrderResource;
use App\Filament\Resources\A2Orders\Pages\ListA2Orders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/** These are intended hosted execution tests, not tests run in the offline authoring sandbox. */
final class A2OrdersInspectTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;
    private User $withoutMembership;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
        $this->withoutMembership = User::factory()->create();

        DB::table('a2_memberships')->insert([
            ['user_id' => $this->owner->getKey(), 'tenant_id' => 'tenant-a', 'can_inspect' => true],
            ['user_id' => $this->other->getKey(), 'tenant_id' => 'tenant-b', 'can_inspect' => true],
        ]);
        DB::table('a2_orders')->insert([
            ['id' => 101, 'tenant_id' => 'tenant-a'],
            ['id' => 201, 'tenant_id' => 'tenant-b'],
        ]);
    }

    public function test_authenticated_owner_inspects_own_order_with_audit(): void
    {
        $before = DB::table('a2_orders')->count();
        Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 101)
            ->assertSet('inspectionStatus', 'ok')
            ->assertSet('inspected', ['orderId' => 101, 'found' => true]);

        self::assertSame($before, DB::table('a2_orders')->count());
        self::assertSame(1, DB::table('surfacerelay_audit_events')
            ->where('action_id', 'a2.orders.inspect')
            ->where('outcome_kind', 'completed')
            ->count());
    }

    public function test_foreign_tenant_order_is_denied(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 201)
            ->assertSet('inspectionStatus', 'denied')
            ->assertSet('inspected', null);
        self::assertSame(1, DB::table('surfacerelay_audit_events')
            ->where('outcome_kind', 'halted')->count());
    }

    public function test_different_actor_can_inspect_only_its_own_tenant(): void
    {
        Livewire::actingAs($this->other)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 201)
            ->assertSet('inspectionStatus', 'ok')
            ->assertSet('inspected', ['orderId' => 201, 'found' => true]);
    }

    public function test_missing_membership_is_denied(): void
    {
        Livewire::actingAs($this->withoutMembership)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 101)
            ->assertSet('inspectionStatus', 'denied')
            ->assertSet('inspected', null);
    }

    public function test_forged_tenant_field_does_not_grant_cross_tenant_access(): void
    {
        $page = Livewire::actingAs($this->owner)->test(ListA2Orders::class)->instance();
        $outcome = app(InspectOrder::class)->dispatch($page, [
            'order_id' => 201,
            'tenant_id' => 'tenant-b',
        ]);
        self::assertFalse($outcome->completed);
        self::assertSame('authorization_denied', $outcome->halt?->code);
    }

    public function test_invalid_order_id_is_rejected_before_execution(): void
    {
        Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 0)
            ->assertSet('inspectionStatus', 'denied')
            ->assertSet('inspected', null);
    }

    public function test_revoked_membership_fails_closed(): void
    {
        DB::table('a2_memberships')
            ->where('user_id', $this->owner->getKey())
            ->where('tenant_id', 'tenant-a')
            ->update(['can_inspect' => false]);

        Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 101)
            ->assertSet('inspectionStatus', 'denied')
            ->assertSet('inspected', null);

        self::assertSame(1, DB::table('surfacerelay_audit_events')
            ->where('action_id', 'a2.orders.inspect')
            ->where('outcome_kind', 'halted')->count());
    }

    public function test_multiple_eligible_tenants_fail_closed_without_implicit_choice(): void
    {
        DB::table('a2_memberships')->insert([
            'user_id' => $this->owner->getKey(),
            'tenant_id' => 'tenant-b',
            'can_inspect' => true,
        ]);

        Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->call('inspectOrder', 101)
            ->assertSet('inspectionStatus', 'denied')
            ->assertSet('inspected', null);

        self::assertSame(1, DB::table('surfacerelay_audit_events')
            ->where('action_id', 'a2.orders.inspect')
            ->where('outcome_kind', 'halted')->count());
    }

    public function test_untrusted_tenant_field_is_not_forwarded_to_authorization(): void
    {
        $page = Livewire::actingAs($this->owner)->test(ListA2Orders::class)->instance();
        $outcome = app(InspectOrder::class)->dispatch($page, [
            'order_id' => 101,
            'tenant_id' => 'tenant-b',
        ]);

        self::assertTrue($outcome->completed);
        self::assertSame(['order_id' => 101], $outcome->state->input);
        self::assertSame(['orderId' => 101, 'found' => true], $outcome->state->output);
    }

    public function test_authenticated_owner_can_mount_filament_orders_page(): void
    {
        $this->actingAs($this->owner)
            ->get(A2OrderResource::getUrl('index'))
            ->assertOk();
    }

    public function test_user_without_membership_cannot_mount_filament_orders_page(): void
    {
        $this->actingAs($this->withoutMembership)
            ->get(A2OrderResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_guest_cannot_mount_filament_orders_page(): void
    {
        $this->get(A2OrderResource::getUrl('index'))
            ->assertRedirect();
    }
}
