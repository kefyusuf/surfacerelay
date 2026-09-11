<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Livewire\Component;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;
use SurfaceRelay\Laravel\Filament\Confirmation\InvalidFilamentConfirmationBridge;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationPage;

final class FilamentConfirmationModalIntegrationTest extends TestCase
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

    public function test_present_mounts_reserved_action_with_exact_locked_state(): void
    {
        $page = $this->page();
        $challenge = $this->challenge('A', 'Approve refund');

        $page->presentSurfaceRelayConfirmation($challenge);

        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
        self::assertSame($challenge->summary, $page->surfaceRelayConfirmationSummary);
        self::assertSame($challenge->expiresAt, $page->surfaceRelayConfirmationExpiresAt);
        self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
    }

    public function test_same_challenge_is_idempotent_and_remounts_after_modal_loss(): void
    {
        $page = $this->page();
        $challenge = $this->challenge('A', 'Approve refund');

        $page->presentSurfaceRelayConfirmation($challenge);
        $page->presentSurfaceRelayConfirmation($challenge);
        self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());

        $page->unmountAction();
        self::assertNull($page->getMountedAction());

        $page->presentSurfaceRelayConfirmation($challenge);

        self::assertSame('surfacerelay_confirmation', $page->getMountedAction()?->getName());
        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
    }

    public function test_different_challenge_cannot_overwrite_existing_presentation(): void
    {
        $page = $this->page();
        $challengeA = $this->challenge('A', 'Approve A');
        $challengeB = $this->challenge('B', 'Approve B');
        $page->presentSurfaceRelayConfirmation($challengeA);

        try {
            $page->presentSurfaceRelayConfirmation($challengeB);
            self::fail('Expected a different challenge to conflict with the existing presentation.');
        } catch (InvalidFilamentConfirmationBridge $e) {
            self::assertSame(
                'Filament confirmation presentation conflicts with active page state.',
                $e->getMessage(),
            );
            self::assertNull($e->getPrevious());
        }

        self::assertSame($challengeA->challengeId, $page->surfaceRelayConfirmationChallengeId);
        self::assertSame($challengeA->summary, $page->surfaceRelayConfirmationSummary);
    }

    public function test_unrelated_mounted_action_is_not_replaced_by_confirmation_presentation(): void
    {
        $page = $this->page();
        $page->cacheAction($page->unrelatedAction());
        $page->mountAction('unrelated');
        self::assertSame('unrelated', $page->getMountedAction()?->getName());

        try {
            $page->presentSurfaceRelayConfirmation($this->challenge('A', 'Approve refund'));
            self::fail('Expected an unrelated mounted action to block confirmation presentation.');
        } catch (InvalidFilamentConfirmationBridge $e) {
            self::assertSame(
                'Filament confirmation presentation conflicts with active page state.',
                $e->getMessage(),
            );
        }

        self::assertSame('unrelated', $page->getMountedAction()?->getName());
        self::assertNull($page->surfaceRelayConfirmationChallengeId);
    }

    public function test_incoherent_stored_presentation_state_fails_closed(): void
    {
        $page = $this->page();
        $page->surfaceRelayConfirmationChallengeId = str_repeat('A', 43);
        $page->surfaceRelayConfirmationSummary = null;
        $page->surfaceRelayConfirmationExpiresAt = null;

        $this->expectException(InvalidFilamentConfirmationBridge::class);
        $this->expectExceptionMessage('Filament confirmation presentation failed.');

        $page->presentSurfaceRelayConfirmation($this->challenge('A', 'Approve refund'));
    }

    public function test_direct_mount_without_server_authored_state_does_not_leave_reserved_action_mounted(): void
    {
        $page = $this->page();

        $page->mountAction('surfacerelay_confirmation');

        self::assertNull($page->getMountedAction());
        self::assertNull($page->surfaceRelayConfirmationChallengeId);
    }

    public function test_livewire_locked_confirmation_properties_reject_browser_mutation(): void
    {
        foreach ([
            'surfaceRelayConfirmationChallengeId' => str_repeat('X', 43),
            'surfaceRelayConfirmationSummary' => 'tampered summary',
            'surfaceRelayConfirmationExpiresAt' => '2099-01-01T00:00:00Z',
        ] as $property => $value) {
            try {
                Livewire::test(LockedConfirmationProbe::class)->set($property, $value);
                self::fail($property . ' must reject client-side mutation.');
            } catch (CannotUpdateLockedPropertyException) {
                self::assertTrue(true);
            }
        }
    }

    private function page(): TestConfirmationPage
    {
        $page = new TestConfirmationPage();
        $page->bootedInteractsWithActions();

        return $page;
    }

    private function challenge(string $seed, string $summary): ConfirmationChallenge
    {
        return new ConfirmationChallenge(
            str_repeat($seed, 43),
            $summary,
            '2026-09-11T04:00:00Z',
        );
    }
}

final class LockedConfirmationProbe extends Component
{
    use InteractsWithSurfaceRelayConfirmation;

    public function render(): string
    {
        return '<div>locked-confirmation-probe</div>';
    }
}
