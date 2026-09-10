<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Filament\Context\InvalidFilamentRecordContext;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;

final class FilamentRecordContextResolverTest extends TestCase
{
    public function test_non_record_page_resolves_to_absence(): void
    {
        self::assertNull((new FilamentRecordContextResolver(new ResolverNonRecordPage()))->resolve());
    }

    public function test_record_page_returns_exact_model_instance_and_minimal_provenance(): void
    {
        $record = $this->record(41, 'SECRET-ATTRIBUTE');
        $resolved = $this->resolve($record);

        self::assertSame($record, $resolved->value);
        self::assertSame('filament.current_record', $resolved->provenance->provider);
        self::assertNull($resolved->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $resolved->confirmationScopeKey);

        $provenance = json_encode([
            $resolved->provenance->provider,
            $resolved->provenance->reference,
        ], JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('41', $provenance);
        self::assertStringNotContainsString('SECRET-ATTRIBUTE', $provenance);
    }

    public function test_identity_is_stable_for_same_typed_record_identity_and_ignores_attributes(): void
    {
        self::assertSame(
            $this->scopeKey($this->record(123, 'alpha')),
            $this->scopeKey($this->record(123, 'beta')),
        );
    }

    public function test_identity_changes_for_different_record_key(): void
    {
        self::assertNotSame(
            $this->scopeKey($this->record(123)),
            $this->scopeKey($this->record(124)),
        );
    }

    public function test_integer_and_string_keys_are_distinct(): void
    {
        self::assertNotSame(
            $this->scopeKey($this->record(123)),
            $this->scopeKey($this->record('123')),
        );
    }

    public function test_exact_model_class_participates_in_identity(): void
    {
        $other = new ResolverOtherRecord();
        $other->setRawAttributes(['id' => 123, 'name' => 'same']);
        $other->exists = true;

        self::assertNotSame(
            $this->scopeKey($this->record(123)),
            $this->scopeKey($other),
        );
    }

    public function test_integer_zero_key_is_valid(): void
    {
        self::assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/D',
            $this->scopeKey($this->record(0)),
        );
    }

    public function test_null_record_from_record_capable_page_fails_closed(): void
    {
        $this->expectException(InvalidFilamentRecordContext::class);
        $this->expectExceptionMessage('Filament current record state is invalid.');

        (new FilamentRecordContextResolver(new ResolverRecordPage()))->resolve();
    }

    public function test_non_model_record_state_fails_closed(): void
    {
        $page = new ResolverRecordPage();
        $page->resolvedRecord = 'not-a-model';

        $this->expectException(InvalidFilamentRecordContext::class);
        $this->expectExceptionMessage('Filament current record state is invalid.');

        (new FilamentRecordContextResolver($page))->resolve();
    }

    public function test_unsaved_model_fails_closed(): void
    {
        $record = new ResolverRecord();
        $record->setRawAttributes(['id' => 55]);
        $record->exists = false;

        $this->expectException(InvalidFilamentRecordContext::class);
        $this->expectExceptionMessage('Filament current record state is invalid.');

        $this->resolve($record);
    }

    public function test_null_key_fails_closed(): void
    {
        $this->expectException(InvalidFilamentRecordContext::class);
        $this->expectExceptionMessage('Filament current record identity is invalid.');

        $this->resolve($this->record(null));
    }

    public function test_empty_string_key_fails_closed(): void
    {
        $this->expectException(InvalidFilamentRecordContext::class);
        $this->expectExceptionMessage('Filament current record identity is invalid.');

        $this->resolve($this->record(''));
    }

    public function test_array_and_object_keys_fail_closed_without_leaking_values(): void
    {
        foreach ([['SECRET-ARRAY-KEY'], (object) ['secret' => 'SECRET-OBJECT-KEY']] as $key) {
            try {
                $this->resolve($this->record($key));
                self::fail('Expected InvalidFilamentRecordContext.');
            } catch (InvalidFilamentRecordContext $exception) {
                self::assertSame('Filament current record identity is invalid.', $exception->getMessage());
                self::assertNull($exception->getPrevious());
                self::assertStringNotContainsString('SECRET-', $exception->getMessage());
            }
        }
    }

    public function test_empty_key_name_fails_closed(): void
    {
        $record = new ResolverEmptyKeyNameRecord();
        $record->setRawAttributes(['' => 'RAW-KEY-DO-NOT-LEAK']);
        $record->exists = true;

        try {
            $this->resolve($record);
            self::fail('Expected InvalidFilamentRecordContext.');
        } catch (InvalidFilamentRecordContext $exception) {
            self::assertSame('Filament current record identity is invalid.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('RAW-KEY-DO-NOT-LEAK', $exception->getMessage());
        }
    }

    public function test_get_record_exception_is_replaced_with_static_safe_failure(): void
    {
        try {
            (new FilamentRecordContextResolver(new ResolverThrowingRecordPage()))->resolve();
            self::fail('Expected InvalidFilamentRecordContext.');
        } catch (InvalidFilamentRecordContext $exception) {
            self::assertSame('Filament current record resolution failed.', $exception->getMessage());
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('SECRET-RECORD-ERROR', $exception->getMessage());
        }
    }

    private function record(mixed $key, string $name = 'name'): ResolverRecord
    {
        $record = new ResolverRecord();
        $record->setRawAttributes(['id' => $key, 'name' => $name]);
        $record->exists = true;

        return $record;
    }

    private function resolve(Model $record): ResolvedTrustedValue
    {
        $page = new ResolverRecordPage();
        $page->resolvedRecord = $record;

        $resolved = (new FilamentRecordContextResolver($page))->resolve();
        self::assertNotNull($resolved);

        return $resolved;
    }

    private function scopeKey(Model $record): string
    {
        return $this->resolve($record)->confirmationScopeKey ?? self::fail('Expected scope key.');
    }
}

final class ResolverRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class ResolverOtherRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class ResolverEmptyKeyNameRecord extends Model
{
    protected $guarded = [];

    protected $primaryKey = '';

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

final class ResolverThrowingRecordPage extends Page
{
    protected static string $resource = ResolverResource::class;

    protected string $view = 'resolver-throwing-record';

    public function getRecord(): mixed
    {
        throw new \RuntimeException('SECRET-RECORD-ERROR');
    }
}
