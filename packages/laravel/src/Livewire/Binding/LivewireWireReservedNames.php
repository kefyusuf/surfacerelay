<?php

declare(strict_types=1);

namespace SurfaceRelay\Laravel\Livewire\Binding;

/**
 * Public/unprefixed names that Livewire's $wire Proxy resolves before normal
 * server-method fallback, plus Proxy-special names that are not safe targets.
 */
final class LivewireWireReservedNames
{
    /** @var list<string> */
    private const array NAMES = [
        'on',
        'el',
        'id',
        'js',
        'get',
        'set',
        'refs',
        'call',
        'hook',
        'watch',
        'dirty',
        'effect',
        'commit',
        'errors',
        'island',
        'upload',
        'entangle',
        'dispatch',
        'intercept',
        'interceptAction',
        'interceptMessage',
        'interceptRequest',
        'dispatchTo',
        'dispatchSelf',
        'dispatchEl',
        'dispatchRef',
        'removeUpload',
        'cancelUpload',
        'uploadMultiple',
        'then',
        'toJSON',
        '__instance',
    ];

    public static function contains(string $method): bool
    {
        return in_array($method, self::NAMES, true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::NAMES;
    }
}
