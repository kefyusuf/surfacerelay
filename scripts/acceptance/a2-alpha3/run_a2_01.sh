#!/usr/bin/env bash
# Creates one fresh disposable Laravel+Filament app; never modifies SurfaceRelay source.
set -Eeuo pipefail
kit_dir="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd -P)"
if [[ $# -ne 1 ]]; then
  echo 'Usage: bash run_a2_01.sh /absolute/new/test-directory' >&2
  exit 2
fi
case "$1" in /*) ;; *) echo 'Target path must be absolute.' >&2; exit 2;; esac
root="$1"
if [[ -e "$root" || -L "$root" ]]; then
  echo "Refusing to overwrite existing path: $root" >&2
  exit 2
fi
# Never install if this disposable runner lacks prerequisites.
python3 "$kit_dir/preflight.py"
mkdir -p "$root"
app="$root/app"
printf '%s\n' 'Installing clean Laravel 13 application and public package versions.'
composer create-project 'laravel/laravel' "$app" '^13.0' --no-interaction --no-progress --prefer-dist
(
  cd -- "$app"
  composer require 'filament/filament:^5.0' 'surfacerelay/laravel:0.1.0-alpha.3' --no-interaction --no-progress --prefer-dist
  php artisan filament:install --panels --no-interaction
  npm install --save-exact '@surfacerelay/browser-runtime@0.1.0-alpha.3' --no-audit --no-fund
  npm run build
  node --input-type=module -e "import { FilamentBrowserDriver, FilamentSelectionCoordinator, GlobalFilamentSelectionRuntime, DriverRegistry } from '@surfacerelay/browser-runtime'; if (![FilamentBrowserDriver,FilamentSelectionCoordinator,GlobalFilamentSelectionRuntime,DriverRegistry].every(x=>typeof x==='function')) process.exit(1); console.log('A2-01 root exports: PASS')"
  php -r "require 'vendor/autoload.php'; if (!class_exists('SurfaceRelay\\Laravel\\SurfaceRelayServiceProvider')) exit(1); echo 'A2-01 Composer autoload: PASS'.PHP_EOL;"
)
python3 "$kit_dir/verify_install.py" "$app" | tee "$root/a2-01-lock-verification.json"
python3 "$kit_dir/verify_payload.py" "$app" | tee "$root/a2-01-critical-payload-verification.json"
printf '\n%s\n' 'A2-01 bootstrap completed. This is not A2-02: no production ActionBus or WebMCP integration has been claimed.'
