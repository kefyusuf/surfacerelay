<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentRecordContext;

final class FilamentRecordIdentityFailureTest extends TestCase
{
    public function test_model_identity_exception_is_replaced_with_static_safe_failure(): void
    {
        $record = new ThrowingIdentityRecord();
        $record->exists = true;

        $page = new ThrowingIdentityPage();
        $page->resolvedRecord = $record;

        try {
            (new FilamentRecordContextResolver($page))->resolve();
            self::fail('Expected InvalidFilamentRecordContext.');
        } catch (InvalidFilamentRecordContext $exception) {
            self::assertSame('Filament current record identity is invalid.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('SECRET-IDENTITY-ERROR', $exception->getMessage());
        }
    }
}

final class ThrowingIdentityRecord extends Model
{
    public $timestamps = false;

    public function getKey()
    {
        throw new \RuntimeException('SECRET-IDENTITY-ERROR');
    }
}

final class ThrowingIdentityResource extends Resource
{
    protected static ?string $model = ThrowingIdentityRecord::class;

    public static function getPages(): array
    {
        return [];
    }
}

final class ThrowingIdentityPage extends Page
{
    protected static string $resource = ThrowingIdentityResource::class;

    protected string $view = 'throwing-identity';

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}
