<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;

final class FilamentRecordContextResolver
{
    private const string IDENTITY_DOMAIN = "surfacerelay.filament.current_record.v1\n";

    private readonly RuntimeScopeCanonicalizer $canonicalizer;

    public function __construct(
        private readonly Page $page,
        ?RuntimeScopeCanonicalizer $canonicalizer = null,
    ) {
        $this->canonicalizer = $canonicalizer ?? new RuntimeScopeCanonicalizer();
    }

    public function resolve(): ?ResolvedTrustedValue
    {
        if (
            ! method_exists($this->page, 'getRecord')
            || ! is_callable([$this->page, 'getRecord'])
        ) {
            return null;
        }

        try {
            $record = $this->page->getRecord();
        } catch (\Throwable) {
            throw InvalidFilamentRecordContext::recordResolutionFailed();
        }

        if (! $record instanceof Model || ! $record->exists) {
            throw InvalidFilamentRecordContext::invalidRecordState();
        }

        try {
            $identity = FilamentRecordIdentity::fromModel($record);
            $encodedIdentity = $identity->encode(
                $this->canonicalizer,
                'filament.current_record.identity',
            );
        } catch (InvalidFilamentRecordIdentity) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        return new ResolvedTrustedValue(
            value: $record,
            provenance: new ContextProvenance('filament.current_record'),
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $encodedIdentity),
        );
    }
}
