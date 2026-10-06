<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Serve only the explicitly built browser assets through PHP's static handler.
if (is_string($path) && str_starts_with($path, '/assets/')
    && !str_contains($path, '..') && is_file(__DIR__ . $path)) {
    return false;
}
require __DIR__ . '/index.php';
