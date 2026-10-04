<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use ReflectionMethod;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationPage;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMemoryStore;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMutableClock;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationSequenceTokenGenerator;

/**
 * D-076: an approved receipt stays server-side, bound to the exact page
 * component, until that page's own retry pulls it once.
 */
final class FilamentConfirmationApprovedReceiptIntegrationTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [LivewireServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', '0123456789abcdef0123456789abcdef');
        $app['config']->set('view.paths', [dirname(__DIR__) . '/Fixtures/views']);
    }

    public function test_approval_keeps_the_receipt_for_one_retry_by_the_same_page(): void
    {
        $service = $this->service('A');
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page('page-1');
        $page->presentSurfaceRelayConfirmation($challenge);

        $page->callMountedAction();

        $receipt = $page->pullApprovedReceiptForTest();
        self::assertIsString($receipt);
        self::assertNotSame($challenge->challengeId, $receipt);
        self::assertNull($page->pullApprovedReceiptForTest());
        self::assertTrue($service->consumeReceipt($receipt, 'scope-A'));
    }

    public function test_approved_receipt_never_enters_public_component_state(): void
    {
        $service = $this->service('A');
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page('page-1');
        $page->presentSurfaceRelayConfirmation($challenge);

        $page->callMountedAction();

        $public = json_encode(get_object_vars($page), JSON_PARTIAL_OUTPUT_ON_ERROR);
        self::assertIsString($public);
        self::assertStringNotContainsString($challenge->challengeId, $public);
    }

    public function test_receipt_is_bound_to_the_approving_component(): void
    {
        $service = $this->service('A');
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $approving = $this->page('page-1');
        $approving->presentSurfaceRelayConfirmation($challenge);
        $approving->callMountedAction();

        self::assertNull($this->page('page-2')->pullApprovedReceiptForTest());
        self::assertIsString($approving->pullApprovedReceiptForTest());
    }

    public function test_cancelled_or_unapprovable_challenges_leave_no_receipt(): void
    {
        $service = $this->service('A', 'B');

        $cancelled = $this->page('page-cancel');
        $cancelled->presentSurfaceRelayConfirmation($service->issueChallenge('scope-A', 'Approve A'));
        $cancelled->getMountedAction()?->getModalCancelAction()?->call();
        self::assertNull($cancelled->pullApprovedReceiptForTest());

        $expired = $this->page('page-expired');
        $expired->presentSurfaceRelayConfirmation($service->issueChallenge('scope-B', 'Approve B'));
        $this->clock->advance(300);
        $expired->callMountedAction();
        self::assertNull($expired->pullApprovedReceiptForTest());
    }

    public function test_presenting_a_new_challenge_discards_an_unused_approved_receipt(): void
    {
        $service = $this->service('A', 'B');
        $page = $this->page('page-1');
        $page->presentSurfaceRelayConfirmation($service->issueChallenge('scope-A', 'Approve A'));
        $page->callMountedAction();

        $page->presentSurfaceRelayConfirmation($service->issueChallenge('scope-B', 'Approve B'));

        self::assertNull($page->pullApprovedReceiptForTest());
    }

    public function test_a_page_without_component_identity_keeps_no_receipt(): void
    {
        $service = $this->service('A');
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = new TestConfirmationPage();
        $page->bootedInteractsWithActions();
        $page->presentSurfaceRelayConfirmation($challenge);

        $page->callMountedAction();

        self::assertNull($page->pullApprovedReceiptForTest());
        self::assertSame([], array_filter(
            array_keys(session()->all()),
            static fn (string $key): bool => str_starts_with($key, 'surfacerelay.'),
        ));
    }

    public function test_receipt_access_is_not_a_livewire_callable_method(): void
    {
        foreach ((new \ReflectionClass(InteractsWithSurfaceRelayConfirmation::class))->getMethods() as $method) {
            if (! str_contains(strtolower($method->getName()), 'receipt')) {
                continue;
            }

            self::assertFalse(
                $method->isPublic(),
                $method->getName() . ' must not be Livewire-callable from the browser.',
            );
        }

        self::assertTrue(
            (new ReflectionMethod(InteractsWithSurfaceRelayConfirmation::class, 'pullApprovedSurfaceRelayConfirmationReceipt'))->isProtected(),
        );
    }

    private FilamentConfirmationMutableClock $clock;

    private function service(string ...$tokens): ConfirmationService
    {
        $this->clock = new FilamentConfirmationMutableClock();
        $service = new ConfirmationService(
            new FilamentConfirmationMemoryStore(),
            $this->clock,
            new FilamentConfirmationSequenceTokenGenerator(
                array_map(static fn (string $token): string => str_repeat($token, 43), $tokens),
            ),
        );
        $this->app->instance(ConfirmationService::class, $service);

        return $service;
    }

    private function page(string $id): TestConfirmationPage
    {
        $page = new TestConfirmationPage();
        $page->setId($id);
        $page->bootedInteractsWithActions();

        return $page;
    }
}
