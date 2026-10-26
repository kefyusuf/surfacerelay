<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\A2\InspectOrder;
use App\Filament\Resources\A2Orders\Pages\ListA2Orders;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * B2 only: the mounted Filament page must issue the exact app-owned browser
 * descriptor document, and issuing it must grant no invocation authority.
 * Browser registration and real Chrome discovery/invocation remain NOT RUN.
 */
final class A2BrowserEmissionTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->owner = User::factory()->create();
        DB::table('a2_memberships')->insert([
            ['user_id' => $this->owner->getKey(), 'tenant_id' => 'tenant-a', 'can_inspect' => true],
        ]);
        DB::table('a2_orders')->insert([
            ['id' => 101, 'tenant_id' => 'tenant-a'],
        ]);
    }

    public function test_mounted_page_issues_exact_browser_descriptor_document(): void
    {
        $page = Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->instance();

        $descriptor = $page->surfacerelayDescriptor;
        self::assertNotNull($descriptor);

        $definition = InspectOrder::definition();
        self::assertSame([
            'id' => $definition->id,
            'version' => $definition->version,
            'title' => $definition->title,
            'description' => $definition->description,
            'inputSchema' => $definition->inputSchema,
            'outputSchema' => $definition->outputSchema,
            'scope' => 'page_scoped',
            'effect' => 'read',
            'risk' => 'low',
            'idempotency' => 'none',
            'outputSensitivity' => 'normal',
            'outputContentTrust' => 'trusted_application_data',
            'contextRequirements' => ['authenticated_actor', 'tenant'],
        ], $descriptor['definition']);

        $binding = $descriptor['binding'];
        self::assertMatchesRegularExpression('/^sr_[a-f0-9]{32}$/', $binding['bindingId']);
        self::assertSame(['id' => 'a2.orders.inspect', 'version' => 1], $binding['action']);
        self::assertSame('livewire', $binding['driver']);
        self::assertSame('component', $binding['lifecycle']);
        self::assertSame([
            'componentId' => $page->getId(),
            'method' => 'inspectOrder',
            'inputOrder' => ['order_id'],
            'requiredCount' => 1,
        ], $binding['target']);
        self::assertNull($binding['expiresAt']);

        $reflection = new \ReflectionProperty(ListA2Orders::class, 'surfacerelayDescriptor');
        self::assertNotEmpty($reflection->getAttributes(Locked::class));

        // A separately mounted instance must receive its own component-scoped
        // binding identity; descriptors are never shared or inherited.
        $second = Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->instance();
        self::assertNotNull($second->surfacerelayDescriptor);
        self::assertNotSame(
            $descriptor['binding']['bindingId'],
            $second->surfacerelayDescriptor['binding']['bindingId'],
        );
        self::assertNotSame(
            $descriptor['binding']['target']['componentId'],
            $second->surfacerelayDescriptor['binding']['target']['componentId'],
        );
    }

    public function test_descriptor_document_shape_matches_published_runtime_contract(): void
    {
        $page = Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->instance();

        $descriptor = $page->surfacerelayDescriptor;
        self::assertNotNull($descriptor);
        self::assertSame(['definition', 'binding'], array_keys($descriptor));
        self::assertSame(
            ['bindingId', 'action', 'driver', 'lifecycle', 'target', 'expiresAt'],
            array_keys($descriptor['binding']),
        );
        self::assertSame(
            [
                'id', 'version', 'title', 'description', 'inputSchema', 'outputSchema', 'scope',
                'effect', 'risk', 'idempotency', 'outputSensitivity', 'outputContentTrust', 'contextRequirements',
            ],
            array_keys($descriptor['definition']),
        );

        // The document must survive the exact JSON round trip the page emits.
        $decoded = json_decode(
            json_encode($descriptor, JSON_THROW_ON_ERROR),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
        self::assertSame($descriptor, $decoded);

        // CI-only handoff: the same server-issued document drives the Node B2
        // suite. The test never accepts a caller-provided path: any non-empty
        // A2_DESCRIPTOR_OUT exports to one fixed location under the trusted
        // consumer storage path, so traversal is structurally impossible.
        $flag = getenv('A2_DESCRIPTOR_OUT');
        if (is_string($flag) && $flag !== '') {
            $target = storage_path('framework/surfacerelay/a2-descriptor.json');
            $directory = dirname($target);
            if (! is_dir($directory)) {
                mkdir($directory, 0777, true);
            }
            file_put_contents($target, json_encode($descriptor, JSON_THROW_ON_ERROR) . "\n");
        }
    }

    public function test_descriptor_issuance_grants_no_invocation_authority(): void
    {
        $page = Livewire::actingAs($this->owner)
            ->test(ListA2Orders::class)
            ->instance();
        self::assertNotNull($page->surfacerelayDescriptor);

        // A user without any membership gets the same denial whether or not a
        // descriptor exists: discovery metadata is not authorization.
        $outsider = User::factory()->create();
        $this->actingAs($outsider);
        $outcome = app(InspectOrder::class)->dispatch($page, ['order_id' => 101]);

        self::assertFalse($outcome->completed);
        self::assertSame('idle', $page->inspectionStatus);
        self::assertNull($page->inspected);
    }
}
