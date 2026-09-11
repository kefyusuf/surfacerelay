<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Fixtures\Filament;

use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use SurfaceRelay\Laravel\Filament\Confirmation\InteractsWithSurfaceRelayConfirmation;
use SurfaceRelay\Laravel\Result\ConfirmationChallenge;

final class TestConfirmationPage extends Page
{
    use InteractsWithSurfaceRelayConfirmation;

    protected static string $resource = TestRecordResource::class;

    protected string $view = 'filament-test-confirmation-page';

    public function unrelatedAction(): Action
    {
        return Action::make('unrelated')
            ->requiresConfirmation()
            ->action(static fn (): null => null);
    }

    public function seedConfirmationForTest(string $id, string $summary, ?string $expiresAt): void
    {
        $this->presentSurfaceRelayConfirmation(new ConfirmationChallenge($id, $summary, $expiresAt));
    }
}
