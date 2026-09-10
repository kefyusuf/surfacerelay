<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Filament\Context;

use Illuminate\Database\Eloquent\Model;
use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final readonly class FilamentRecordIdentity
{
    private function __construct(
        public string $modelClass,
        public string $keyName,
        public int|string $keyValue,
    ) {}

    public static function fromModel(Model $record): self
    {
        if (! $record->exists) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        try {
            $keyName = $record->getKeyName();
            $keyValue = $record->getKey();
        } catch (\Throwable) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        if (
            ! is_string($keyName)
            || $keyName === ''
            || (! is_int($keyValue) && ! is_string($keyValue))
            || $keyValue === ''
        ) {
            throw InvalidFilamentRecordIdentity::invalid();
        }

        return new self($record::class, $keyName, $keyValue);
    }

    /** @return array{modelClass:string,keyName:string,keyValue:int|string} */
    public function payload(): array
    {
        return [
            'modelClass' => $this->modelClass,
            'keyName' => $this->keyName,
            'keyValue' => $this->keyValue,
        ];
    }

    public function encode(RuntimeScopeCanonicalizer $canonicalizer, string $path): string
    {
        try {
            return $canonicalizer->encode($this->payload(), $path);
        } catch (UnrepresentableRuntimeScope) {
            throw InvalidFilamentRecordIdentity::invalid();
        }
    }
}
