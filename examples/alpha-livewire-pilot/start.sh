#!/bin/sh
set -eu
cp -R /source/. /tmp/pilot/
cd /tmp/pilot
test -f composer.lock && test -f package-lock.json
composer install --no-dev --no-interaction --prefer-dist --no-progress
npm ci --no-audit --no-fund
npm run build
php setup.php
exec php -S 0.0.0.0:8000 -t public public/router.php
