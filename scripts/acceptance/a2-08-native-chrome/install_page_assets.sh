#!/usr/bin/env bash
# A2-08 stage 2: install page assets from registry-installed bytes, migrate
# and seed. Resumable; install_overlay_and_assets.sh runs this after the
# strict one-shot overlay. Usage: install_page_assets.sh <instance-root> <repo-mount>
set -Eeuo pipefail

INSTANCE="${1:?Usage: install_page_assets.sh <instance-root> <repo-mount>}"
REPO="${2:?Usage: install_page_assets.sh <instance-root> <repo-mount>}"
app="$INSTANCE/app"

# Local-iteration sync: force-copy the overlay-owned app tree from the repo so
# the consumer copy always matches the reviewed source. The strict one-shot
# install_overlay.py remains the CI gate; this stage only refreshes files.
cp -rf "$REPO/scripts/acceptance/a2-02-read-pilot/app/." "$app/app/"

mkdir -p "$app/public/js/browser-runtime"
cp -- "$REPO/scripts/acceptance/a2-02-browser-pilot/browser/a2-surfacerelay-glue.mjs" "$app/public/js/a2-surfacerelay-glue.mjs"
cp -- "$REPO/scripts/acceptance/a2-08-native-chrome/a2-surfacerelay-page.mjs" "$app/public/js/a2-surfacerelay-page.mjs"
# The served runtime must be the exact published bytes installed by the registry;
# the published dist is a relative module tree, so the whole directory is served.
cp "$app/node_modules/@surfacerelay/browser-runtime/dist/"*.js "$app/public/js/browser-runtime/"
cp -- "$REPO/scripts/acceptance/a2-08-native-chrome/audit_count.php" "$INSTANCE/a2-08-audit-count.php"
sha256sum \
  "$app/public/js/a2-surfacerelay-glue.mjs" \
  "$app/public/js/a2-surfacerelay-page.mjs" \
  "$app/public/js/browser-runtime/index.js" \
  > "$INSTANCE/a2-08-page-assets-sha256.txt"
cat "$INSTANCE/a2-08-page-assets-sha256.txt"

cd "$app"
php artisan migrate --force
php "$REPO/scripts/acceptance/a2-08-native-chrome/seed_consumer.php"
echo "A2-08 page assets, migration and seed complete."
