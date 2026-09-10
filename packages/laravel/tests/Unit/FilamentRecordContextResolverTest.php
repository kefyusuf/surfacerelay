<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;

final class FilamentRecordContextResolverTest extends TestCase
{
    public function test_non_record_page_resolves_to_absence(): void
    {
        self::assertNull((new FilamentRecordContextResolver(new ResolverNonRecordPage()))->resolve());
    }

    public function test_record_page_returns_exact_model_instance_and_minimal_provenance(): void
    {
        $record = new ResolverRecord();
        $record->setRawAttributes(['id' => 41, 'name' => 'SECRET-ATTRIBUTE']);
        $record->exists = true;

        $page = new ResolverRecordPage();
        $page->resolvedRecord = $record;

        $resolved = (new FilamentRecordContextResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertSame($record, $resolved->value);
        self::assertSame('filament.current_record', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);
    }
}

final class ResolverRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class ResolverResource extends Resource
{
    protected static ?string $model = ResolverRecord::class;

    public static function getPages(): array
    {
        return [];
    }
}

final class ResolverNonRecordPage extends Page
{
    protected static string $resource = ResolverResource::class;

    protected string $view = 'resolver-non-record';
}

final class ResolverRecordPage extends Page
{
    protected static string $resource = ResolverResource::class;

    protected string $view = 'resolver-record';

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}
