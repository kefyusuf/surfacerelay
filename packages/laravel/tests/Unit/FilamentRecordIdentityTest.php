<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SurfaceRelay\Laravel\Filament\Context\FilamentRecordIdentity;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Tests\Fixtures\Filament\TestRecord;

final class FilamentRecordIdentityTest extends TestCase
{
    public function test_exact_persisted_record_identity_has_canonical_payload_and_encoding(): void
    {
        $record = new TestRecord();
        $record->setRawAttributes(['id' => 41, 'name' => 'ignored']);
        $record->exists = true;

        $identity = FilamentRecordIdentity::fromModel($record);
        $canonicalizer = new RuntimeScopeCanonicalizer();

        self::assertSame([
            'modelClass' => TestRecord::class,
            'keyName' => 'id',
            'keyValue' => 41,
        ], $identity->payload());
        self::assertSame(
            $canonicalizer->encode($identity->payload(), 'test.identity'),
            $identity->encode($canonicalizer, 'test.identity'),
        );
    }
}
