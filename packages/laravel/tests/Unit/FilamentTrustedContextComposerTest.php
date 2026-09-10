<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Contracts\AuthenticatedActorResolver;
use SurfaceRelay\Laravel\Contracts\TenantResolver;
use SurfaceRelay\Laravel\Enums\ContextRequirement;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Filament\Context\FilamentTrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextComposer;
use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;
use SurfaceRelay\Laravel\Runtime\InvocationContext;

final class FilamentTrustedContextComposerTest extends TestCase
{
    public function test_composes_actor_tenant_and_exact_current_record_in_canonical_order(): void
    {
        $record = $this->record(77);
        $entries = $this->composer($this->recordPage($record))->resolve();

        self::assertSame(
            ['authenticated_actor', 'tenant', 'current_record'],
            array_map(
                static fn (TrustedContextEntry $entry): string => $entry->requirement->value,
                $entries,
            ),
        );
        self::assertSame('actor-7', $entries[0]->value);
        self::assertSame('tenant-A', $entries[1]->value);
        self::assertSame($record, $entries[2]->value);
        self::assertSame('filament.current_record', $entries[2]->provenance->provider);
        self::assertNull($entries[2]->provenance->reference);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $entries[2]->confirmationScopeKey);
    }

    public function test_non_record_page_preserves_base_actor_and_tenant_entries_only(): void
    {
        $entries = $this->composer(new ComposerNonRecordPage())->resolve();

        self::assertSame(
            ['authenticated_actor', 'tenant'],
            array_map(
                static fn (TrustedContextEntry $entry): string => $entry->requirement->value,
                $entries,
            ),
        );
    }

    public function test_invocation_context_keeps_record_authority_even_when_metadata_spoofs_record_fields(): void
    {
        $record = $this->record(88);
        $context = new InvocationContext(
            surface: 'filament',
            correlationId: 'corr-record-88',
            trustedContext: $this->composer($this->recordPage($record))->resolve(),
            metadata: [
                'current_record' => 'attacker-record',
                'recordId' => 999,
                'id' => 999,
            ],
        );

        $entry = $context->require(ContextRequirement::CurrentRecord);
        self::assertSame($record, $entry->value);
        self::assertSame('filament.current_record', $entry->provenance->provider);
        self::assertSame('attacker-record', $context->metadata['current_record']);
    }

    private function composer(Page $page): FilamentTrustedContextComposer
    {
        return new FilamentTrustedContextComposer(
            new TrustedContextComposer(
                new ComposerActorResolver('actor-7'),
                new ComposerTenantResolver('tenant-A'),
            ),
            new FilamentRecordContextResolver($page),
        );
    }

    private function record(int|string $key): ComposerRecord
    {
        $record = new ComposerRecord();
        $record->setRawAttributes(['id' => $key, 'name' => 'record-' . $key]);
        $record->exists = true;

        return $record;
    }

    private function recordPage(Model $record): ComposerRecordPage
    {
        $page = new ComposerRecordPage();
        $page->resolvedRecord = $record;

        return $page;
    }
}

final class ComposerActorResolver implements AuthenticatedActorResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        if ($this->value === null) {
            return null;
        }

        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('test.auth'),
            'actor-scope',
        );
    }
}

final class ComposerTenantResolver implements TenantResolver
{
    public function __construct(private readonly mixed $value) {}

    public function resolve(): ?ResolvedTrustedValue
    {
        if ($this->value === null) {
            return null;
        }

        return new ResolvedTrustedValue(
            $this->value,
            new ContextProvenance('test.tenant'),
            'tenant-scope',
        );
    }
}

final class ComposerRecord extends Model
{
    protected $guarded = [];

    public $timestamps = false;
}

final class ComposerResource extends Resource
{
    protected static ?string $model = ComposerRecord::class;

    public static function getPages(): array
    {
        return [];
    }
}

final class ComposerNonRecordPage extends Page
{
    protected static string $resource = ComposerResource::class;

    protected string $view = 'composer-non-record';
}

final class ComposerRecordPage extends Page
{
    protected static string $resource = ComposerResource::class;

    protected string $view = 'composer-record';

    public mixed $resolvedRecord = null;

    public function getRecord(): mixed
    {
        return $this->resolvedRecord;
    }
}
