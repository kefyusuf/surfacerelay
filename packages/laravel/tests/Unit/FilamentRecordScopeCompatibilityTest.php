<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordContextResolver;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecordPage;

final class FilamentRecordScopeCompatibilityTest extends TestCase
{
    public function test_current_record_scope_hash_is_byte_compatible_before_identity_refactor(): void
    {
        $record = new TestRecord();
        $record->setRawAttributes(['id' => 41, 'name' => 'ignored']);
        $record->exists = true;

        $page = new TestRecordPage();
        $page->record = $record;

        $resolved = (new FilamentRecordContextResolver($page))->resolve();

        self::assertNotNull($resolved);
        self::assertSame(
            '5436c4020c370bb2f495f0954332bd892a3e5f89950db287c808f292e32bfa96',
            $resolved->confirmationScopeKey,
        );
    }
}
