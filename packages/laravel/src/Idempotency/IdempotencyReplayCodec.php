<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Idempotency;

use SurfaceRelay\Laravel\Runtime\Scope\RuntimeScopeCanonicalizer;
use SurfaceRelay\Laravel\Runtime\Scope\UnrepresentableRuntimeScope;

final readonly class IdempotencyReplayCodec
{
    public function __construct(
        private RuntimeScopeCanonicalizer $canonicalizer = new RuntimeScopeCanonicalizer(),
    ) {}

    public function encode(mixed $output): string
    {
        try {
            return $this->canonicalizer->encode($output, 'output');
        } catch (UnrepresentableRuntimeScope $e) {
            throw UnreplayableIdempotencyOutput::at($e->path);
        }
    }

    public function decode(string $payload): mixed
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            return $this->canonicalizer->canonicalize($decoded, 'output');
        } catch (\JsonException|UnrepresentableRuntimeScope) {
            throw CorruptIdempotencyRecord::replayPayload();
        }
    }
}
