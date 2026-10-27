<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\QuickstartOrders\Pages\ListQuickstartOrders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Quickstart acceptance: the documented consumer code dispatches one read-only
 * action through the trusted pipeline, issues the browser descriptor, and
 * fails closed for guests and foreign orders. The browser-side registration
 * semantics are proven separately by the Node glue test; real Chrome
 * interoperability is demonstrated by the A2-08 native proof.
 */
final class QuickstartTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->member = User::factory()->create();
        DB::table('quickstart_orders')->insert([
            ['id' => 101, 'tenant_id' => 'quickstart-tenant', 'status' => 'paid'],
            ['id' => 202, 'tenant_id' => 'other-tenant', 'status' => 'paid'],
        ]);
    }

    public function test_member_dispatch_inspects_own_order_with_audit(): void
    {
        $before = DB::table('surfacerelay_audit_events')->count();

        $page = Livewire::actingAs($this->member)->test(ListQuickstartOrders::class);
        $page->call('inspectOrder', 101);

        self::assertSame('ok', $page->instance()->inspectionStatus);
        self::assertSame(
            ['orderId' => 101, 'status' => 'paid'],
            $page->instance()->inspected,
        );
        self::assertSame(
            $before + 1,
            DB::table('surfacerelay_audit_events')->count(),
        );
    }

    public function test_foreign_tenant_order_is_denied(): void
    {
        $page = Livewire::actingAs($this->member)->test(ListQuickstartOrders::class);
        $page->call('inspectOrder', 202);

        self::assertSame('denied', $page->instance()->inspectionStatus);
        self::assertNull($page->instance()->inspected);
    }

    public function test_forged_extra_field_does_not_become_authority(): void
    {
        $page = Livewire::actingAs($this->member)->test(ListQuickstartOrders::class);

        // The exposed method's signature only accepts order_id; extra caller
        // fields can never reach the pipeline as authority.
        $page->call('inspectOrder', 101, ['tenant_id' => 'other-tenant']);

        self::assertSame('ok', $page->instance()->inspectionStatus);
        self::assertSame(['orderId' => 101, 'status' => 'paid'], $page->instance()->inspected);
    }

    public function test_page_issues_the_browser_descriptor_document(): void
    {
        $page = Livewire::actingAs($this->member)->test(ListQuickstartOrders::class);
        $instance = $page->instance();

        $descriptor = $instance->surfacerelayDescriptor;
        self::assertNotNull($descriptor);
        self::assertSame('quickstart.orders.inspect', $descriptor['definition']['id']);
        self::assertSame(1, $descriptor['definition']['version']);
        self::assertSame('livewire', $descriptor['binding']['driver']);
        self::assertSame('component', $descriptor['binding']['lifecycle']);
        self::assertSame($instance->getId(), $descriptor['binding']['target']['componentId']);
        self::assertSame('inspectOrder', $descriptor['binding']['target']['method']);

        // The descriptor is client-visible snapshot data (Locked prevents mutation).
        self::assertStringContainsString('surfacerelayDescriptor', $page->html());
    }

    public function test_guest_cannot_mount_the_quickstart_page(): void
    {
        $this->expectException(\Illuminate\Auth\AuthenticationException::class);

        Livewire::test(ListQuickstartOrders::class);
    }
}
