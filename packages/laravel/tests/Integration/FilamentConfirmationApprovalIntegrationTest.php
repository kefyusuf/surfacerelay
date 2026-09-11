<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Integration;

use Filament\Notifications\Notification;
use Livewire\LivewireServiceProvider;
use Orchestra\Testbench\TestCase;
use SurfaceRelay\Laravel\Confirmation\ConfirmationRecordState;
use SurfaceRelay\Laravel\Confirmation\ConfirmationService;
use SurfaceRelay\Laravel\Filament\Confirmation\InvalidFilamentConfirmationBridge;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestConfirmationPage;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMemoryStore;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationMutableClock;
use SurfaceRelay\Laravel\Tests\Support\FilamentConfirmationSequenceTokenGenerator;

final class FilamentConfirmationApprovalIntegrationTest extends TestCase
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

    public function test_approve_uses_only_locked_challenge_and_ignores_browser_action_arguments(): void
    {
        [$service, $store] = $this->service('A', 'B');
        $this->app->instance(ConfirmationService::class, $service);

        $challengeA = $service->issueChallenge('scope-A', 'Approve A');
        $challengeB = $service->issueChallenge('scope-B', 'Approve B');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challengeA);

        $result = $page->callMountedAction([
            'challengeId' => $challengeB->challengeId,
            'confirmationReceipt' => $challengeB->challengeId,
        ]);

        self::assertNull($result);
        self::assertSame(
            ConfirmationRecordState::Approved,
            $store->recordForToken($challengeA->challengeId)?->state,
        );
        self::assertSame(
            ConfirmationRecordState::Pending,
            $store->recordForToken($challengeB->challengeId)?->state,
        );
        $this->assertPresentationCleared($page);
        Notification::assertNotified(
            Notification::make()
                ->success()
                ->title('Confirmation approved. Retry the original operation.'),
        );
    }

    public function test_cancel_clears_ui_state_without_approving_core_challenge(): void
    {
        [$service, $store] = $this->service('A');
        $this->app->instance(ConfirmationService::class, $service);
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challenge);

        $cancel = $page->getMountedAction()?->getModalCancelAction();
        self::assertNotNull($cancel);
        $cancel->call();

        self::assertSame(
            ConfirmationRecordState::Pending,
            $store->recordForToken($challenge->challengeId)?->state,
        );
        $this->assertPresentationCleared($page);
    }

    public function test_missing_confirmation_service_binding_fails_closed(): void
    {
        [$service] = $this->service('A');
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challenge);

        try {
            $page->callMountedAction();
            self::fail('Expected missing ConfirmationService binding to fail closed.');
        } catch (InvalidFilamentConfirmationBridge $e) {
            self::assertSame('Filament confirmation service is unavailable.', $e->getMessage());
            self::assertNull($e->getPrevious());
        }

        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
    }

    public function test_expired_or_non_pending_challenge_clears_stale_ui_without_token_oracle(): void
    {
        [$service, $store, $clock] = $this->service('A');
        $this->app->instance(ConfirmationService::class, $service);
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challenge);

        $clock->advance(300);
        $result = $page->callMountedAction();

        self::assertNull($result);
        self::assertSame(
            ConfirmationRecordState::Pending,
            $store->recordForToken($challenge->challengeId)?->state,
        );
        $this->assertPresentationCleared($page);
        Notification::assertNotified(
            Notification::make()
                ->warning()
                ->title('Confirmation is no longer approvable. Retry the original operation.'),
        );
    }

    public function test_confirmation_store_failure_is_redacted_and_does_not_look_like_approval_success(): void
    {
        [$service, $store] = $this->service('A');
        $this->app->instance(ConfirmationService::class, $service);
        $challenge = $service->issueChallenge('scope-A', 'Approve A');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challenge);
        $store->throwOnApprove = true;

        try {
            $page->callMountedAction();
            self::fail('Expected confirmation-store failure to fail closed.');
        } catch (InvalidFilamentConfirmationBridge $e) {
            self::assertSame('Filament confirmation approval failed.', $e->getMessage());
            self::assertNull($e->getPrevious());
            self::assertStringNotContainsString('confirmation test store unavailable', $e->getMessage());
        }

        self::assertSame(
            ConfirmationRecordState::Pending,
            $store->recordForToken($challenge->challengeId)?->state,
        );
        self::assertSame($challenge->challengeId, $page->surfaceRelayConfirmationChallengeId);
        self::assertSame($challenge->summary, $page->surfaceRelayConfirmationSummary);
    }

    public function test_distinct_confirmation_store_binding_cannot_cross_approve_issued_challenge(): void
    {
        [$issuingService, $issuingStore] = $this->service('A');
        [$bridgeService, $bridgeStore] = $this->service('B');
        $this->app->instance(ConfirmationService::class, $bridgeService);

        $challenge = $issuingService->issueChallenge('scope-A', 'Approve A');
        $page = $this->page();
        $page->presentSurfaceRelayConfirmation($challenge);

        $result = $page->callMountedAction();

        self::assertNull($result);
        self::assertSame(
            ConfirmationRecordState::Pending,
            $issuingStore->recordForToken($challenge->challengeId)?->state,
        );
        self::assertNull($bridgeStore->recordForToken($challenge->challengeId));
        $this->assertPresentationCleared($page);
        Notification::assertNotified(
            Notification::make()
                ->warning()
                ->title('Confirmation is no longer approvable. Retry the original operation.'),
        );
    }

    /**
     * @return array{0: ConfirmationService, 1: FilamentConfirmationMemoryStore, 2: FilamentConfirmationMutableClock}
     */
    private function service(string ...$tokenSeeds): array
    {
        $store = new FilamentConfirmationMemoryStore();
        $clock = new FilamentConfirmationMutableClock();
        $tokens = new FilamentConfirmationSequenceTokenGenerator(array_map(
            static fn (string $seed): string => str_repeat($seed, 43),
            $tokenSeeds,
        ));

        return [
            new ConfirmationService($store, $clock, $tokens),
            $store,
            $clock,
        ];
    }

    private function page(): TestConfirmationPage
    {
        $page = new TestConfirmationPage();
        $page->bootedInteractsWithActions();

        return $page;
    }

    private function assertPresentationCleared(TestConfirmationPage $page): void
    {
        self::assertNull($page->surfaceRelayConfirmationChallengeId);
        self::assertNull($page->surfaceRelayConfirmationSummary);
        self::assertNull($page->surfaceRelayConfirmationExpiresAt);
    }
}
