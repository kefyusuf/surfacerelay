<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\A2Orders\Pages\ListA2Orders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A2-08 page-wiring acceptance (hosted, no Chrome): the mounted Filament page
 * must serve the registration module and embed the server-issued descriptor
 * in the client-visible Livewire snapshot, without issuing any authority.
 * Real Chrome document.modelContext discovery/invocation is the separate
 * local native proof.
 */
final class A2PageWiringTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->member = User::factory()->create();
        DB::table('a2_memberships')->insert([
            ['user_id' => $this->member->getKey(), 'tenant_id' => 'tenant-a', 'can_inspect' => true],
        ]);
        DB::table('a2_orders')->insert([
            ['id' => 101, 'tenant_id' => 'tenant-a'],
        ]);
    }

    public function test_panel_pages_serve_the_a2_registration_module_wiring(): void
    {
        $html = Livewire::actingAs($this->member)
            ->test(ListA2Orders::class)
            ->html();

        self::assertStringContainsString('type="importmap"', $html);
        self::assertStringContainsString('/js/browser-runtime/index.js', $html);
        self::assertStringContainsString('/js/a2-surfacerelay-page.mjs', $html);
    }

    public function test_mounted_page_embeds_the_descriptor_in_the_client_snapshot(): void
    {
        $mounted = Livewire::actingAs($this->member)
            ->test(ListA2Orders::class)
            ->instance();

        $html = Livewire::test(ListA2Orders::class)->html();
        self::assertStringContainsString('surfacerelayDescriptor', $html);
        self::assertStringContainsString('a2.orders.inspect', $html);

        $descriptor = $mounted->surfacerelayDescriptor;
        self::assertNotNull($descriptor);
        self::assertSame('a2.orders.inspect', $descriptor['definition']['id']);
        self::assertSame('livewire', $descriptor['binding']['driver']);
        self::assertSame('component', $descriptor['binding']['lifecycle']);
        self::assertSame($mounted->getId(), $descriptor['binding']['target']['componentId']);
    }
}
