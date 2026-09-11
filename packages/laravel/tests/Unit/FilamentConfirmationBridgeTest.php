<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Filament\Resources\Pages\Page;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Definition\ActionDefinition;
use SurfaceRelay\Laravel\Enums\ActionEffect;
use SurfaceRelay\Laravel\Enums\ActionRisk;
use SurfaceRelay\Laravel\Enums\ActionScope;
use SurfaceRelay\Laravel\Enums\IdempotencyPolicy;
use SurfaceRelay\Laravel\Enums\OutputContentTrust;
use SurfaceRelay\Laravel\Enums\OutputSensitivity;
use SurfaceRelay\Laravel\Filament\Confirmation\FilamentConfirmationBridge;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;
use SurfaceRelay\Laravel\Filament\Confirmation\InvalidFilamentConfirmationBridge;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;
use SurfaceRelay\Laravel\Result\CoreActionErrorCode;
use SurfaceRelay\Laravel\Runtime\InvocationContext;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineHalt;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineOutcome;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineStage;
use SurfaceRelay\Laravel\Runtime\Pipeline\ActionPipelineState;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\NonRecordPage;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecordResource;

final class FilamentConfirmationBridgeTest extends TestCase
{
    public function test_completed_and_non_confirmation_outcomes_are_no_op(): void
    {
        self::assertTrue(
            class_exists(FilamentConfirmationBridge::class),
            'FilamentConfirmationBridge must exist before no-op behavior can be exercised.',
        );

        $page = new NonRecordPage();
        $bridge = new FilamentConfirmationBridge();
        $state = $this->state();

        $bridge->presentIfRequired($page, ActionPipelineOutcome::completed($state));
        $bridge->presentIfRequired($page, ActionPipelineOutcome::halted(
            $state,
            ActionPipelineStage::Authorization,
            new ActionPipelineHalt(CoreActionErrorCode::AUTHORIZATION_DENIED),
        ));

        self::assertTrue(true);
    }

    public function test_confirmation_halt_without_typed_challenge_fails_closed(): void
    {
        self::assertTrue(
            class_exists(FilamentConfirmationBridge::class),
            'FilamentConfirmationBridge must exist before malformed-halt behavior can be exercised.',
        );
        self::assertTrue(
            class_exists(InvalidFilamentConfirmationBridge::class),
            'InvalidFilamentConfirmationBridge must exist before malformed-halt behavior can be exercised.',
        );

        $this->expectException(InvalidFilamentConfirmationBridge::class);
        $this->expectExceptionMessage('Filament confirmation outcome is invalid.');

        (new FilamentConfirmationBridge())->presentIfRequired(
            new NonRecordPage(),
            ActionPipelineOutcome::halted(
                $this->state(),
                ActionPipelineStage::Confirmation,
                new ActionPipelineHalt(CoreActionErrorCode::CONFIRMATION_REQUIRED),
            ),
        );
    }

    public function test_real_confirmation_requires_explicit_page_opt_in(): void
    {
        self::assertTrue(
            class_exists(FilamentConfirmationBridge::class),
            'FilamentConfirmationBridge must exist before opt-in behavior can be exercised.',
        );
        self::assertTrue(
            class_exists(InvalidFilamentConfirmationBridge::class),
            'InvalidFilamentConfirmationBridge must exist before opt-in behavior can be exercised.',
        );

        $challenge = $this->challenge();

        try {
            (new FilamentConfirmationBridge())->presentIfRequired(
                new NonRecordPage(),
                $this->confirmationOutcome($challenge),
            );
            self::fail('Expected an opted-out page to reject confirmation presentation.');
        } catch (InvalidFilamentConfirmationBridge $e) {
            self::assertSame('Filament confirmation bridge is unavailable for this page.', $e->getMessage());
            self::assertNull($e->getPrevious());
        }
    }

    public function test_real_confirmation_delegates_exact_typed_challenge_to_opted_in_page(): void
    {
        self::assertTrue(
            trait_exists(InteractsWithSurfaceRelayConfirmation::class),
            'InteractsWithSurfaceRelayConfirmation must exist before positive delegation can be exercised.',
        );
        self::assertTrue(
            class_exists(FilamentConfirmationBridge::class),
            'FilamentConfirmationBridge must exist before positive delegation can be exercised.',
        );
        self::assertTrue(
            class_exists(CapturingConfirmationPage::class),
            'Capturing confirmation page fixture must be available once the trait exists.',
        );

        $page = new CapturingConfirmationPage();
        $challenge = $this->challenge();

        (new FilamentConfirmationBridge())->presentIfRequired(
            $page,
            $this->confirmationOutcome($challenge),
        );

        self::assertSame($challenge, $page->capturedChallenge);
    }

    private function confirmationOutcome(ConfirmationChallenge $challenge): ActionPipelineOutcome
    {
        return ActionPipelineOutcome::halted(
            $this->state(),
            ActionPipelineStage::Confirmation,
            new ActionPipelineHalt(
                CoreActionErrorCode::CONFIRMATION_REQUIRED,
                confirmation: $challenge,
            ),
        );
    }

    private function challenge(): ConfirmationChallenge
    {
        return new ConfirmationChallenge(
            str_repeat('A', 43),
            'Approve refund',
            '2026-09-11T04:00:00Z',
        );
    }

    private function state(): ActionPipelineState
    {
        return new ActionPipelineState(
            definition: new ActionDefinition(
                id: 'orders.refund.prepare',
                version: 1,
                title: 'Prepare refund',
                description: 'Test definition for the Filament confirmation presentation boundary.',
                inputSchema: ['type' => 'object'],
                scope: ActionScope::PageScoped,
                effect: ActionEffect::ReversibleWrite,
                risk: ActionRisk::Moderate,
                idempotency: IdempotencyPolicy::None,
                outputSensitivity: OutputSensitivity::Normal,
                outputContentTrust: OutputContentTrust::TrustedApplicationData,
                contextRequirements: [],
            ),
            input: [],
            context: new InvocationContext('filament', 'corr-bridge'),
        );
    }
}

if (trait_exists(InteractsWithSurfaceRelayConfirmation::class)) {
    final class CapturingConfirmationPage extends Page
    {
        use InteractsWithSurfaceRelayConfirmation;

        protected static string $resource = TestRecordResource::class;

        protected string $view = 'filament-test-non-record-page';

        public ?ConfirmationChallenge $capturedChallenge = null;

        public function presentSurfaceRelayConfirmation(ConfirmationChallenge $challenge): void
        {
            $this->capturedChallenge = $challenge;
        }
    }
}
