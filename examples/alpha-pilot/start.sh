#!/bin/sh
set -eu
cp -R /source/. /tmp/pilot/
cd /tmp/pilot
composer install --no-dev --no-interaction --prefer-dist --no-progress
npm ci --no-audit --no-fund
npm run build
php setup.php
php checkout_setup.php
php -S 0.0.0.0:8001 -t public public/router.php &
secondary=$!
trap 'kill "$secondary" 2>/dev/null || true' EXIT INT TERM
php -S 0.0.0.0:8000 -t public public/router.php &
primary=$!
trap 'kill "$primary" "$secondary" 2>/dev/null || true' EXIT INT TERM
wait "$primary"
