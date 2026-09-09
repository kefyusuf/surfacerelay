<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Runtime\Scope;

use SurfaceRelay\Laravel\Runtime\Context\TrustedContextEntry;

/** Deterministic protocol-neutral encoding for trusted runtime scope values. */
final class RuntimeScopeCanonicalizer
{
    public function trustedIdentity(TrustedContextEntry $entry, string $path): array
    {
        if ($entry->confirmationScopeKey !== null) {
            return ['scopeKey' => $entry->confirmationScopeKey];
        }

        return ['value' => $this->canonicalize($entry->value, $path)];
    }

    public function encode(mixed $value, string $path): string
    {
        try {
            return json_encode(
                $this->canonicalize($value, $path),
                JSON_THROW_ON_ERROR
                    | JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_PRESERVE_ZERO_FRACTION,
            );
        } catch (\JsonException) {
            throw UnrepresentableRuntimeScope::at($path);
        }
    }

    public function canonicalize(mixed $value, string $path): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_string($value)) {
            return $value;
        }

        if (is_float($value)) {
            if (!is_finite($value)) {
                throw UnrepresentableRuntimeScope::at($path);
            }

            return $value;
        }

        if (!is_array($value)) {
            throw UnrepresentableRuntimeScope::at($path);
        }

        if (array_is_list($value)) {
            $canonical = [];
            foreach ($value as $index => $entry) {
                $canonical[] = $this->canonicalize($entry, $path . '[' . $index . ']');
            }

            return $canonical;
        }

        foreach (array_keys($value) as $key) {
            if (!is_string($key)) {
                throw UnrepresentableRuntimeScope::at($path);
            }
        }

        ksort($value, SORT_STRING);
        $canonical = [];
        foreach ($value as $key => $entry) {
            $canonical[$key] = $this->canonicalize($entry, $path . '.' . $key);
        }

        return $canonical;
    }
}
