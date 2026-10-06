<?php

return [
    'name' => 'SurfaceRelay installed alpha acceptance', 'env' => 'acceptance',
    'debug' => false, 'url' => 'http://127.0.0.1:4181', 'timezone' => 'UTC',
    'locale' => 'en', 'fallback_locale' => 'en', 'cipher' => 'AES-256-CBC',
    // Disposable fixture key, never a deployment credential.
    'key' => 'base64:' . base64_encode(str_repeat('a', 32)),
];
