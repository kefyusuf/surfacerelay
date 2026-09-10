<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Model;
use SurfaceRelay\Laravel\Runtime\Context\ContextProvenance;
use SurfaceRelay\Laravel\Runtime\Context\ResolvedTrustedValue;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

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
            $keyName = $record->getKeyName();
            $keyValue = $record->getKey();
        } catch (\Throwable) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        if (
            ! is_string($keyName)
            || $keyName === ''
            || (! is_int($keyValue) && ! is_string($keyValue))
            || $keyValue === ''
        ) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        try {
            $encodedIdentity = $this->canonicalizer->encode([
                'modelClass' => $record::class,
                'keyName' => $keyName,
                'keyValue' => $keyValue,
            ], 'filament.current_record.identity');
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentRecordContext::invalidRecordIdentity();
        }

        return new ResolvedTrustedValue(
            value: $record,
            provenance: new ContextProvenance('filament.current_record'),
            confirmationScopeKey: hash('sha256', self::IDENTITY_DOMAIN . $encodedIdentity),
        );
    }
}
