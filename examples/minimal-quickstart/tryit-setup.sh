#!/usr/bin/env bash
# T-922 container-side setup for the quickstart try-it flow.
# Runs INSIDE the surfacerelay-tryit image with the repository mounted at
# $REPO (read-only) and a named volume mounted at $CONSUMER. Creates one
# disposable registry consumer, applies the documented quickstart overlay,
# serves the published runtime bytes, migrates, seeds the demo user and
# starts php artisan serve on 0.0.0.0:8080.
set -Eeuo pipefail

CONSUMER="${1:?Usage: tryit-setup.sh <consumer-root> <repo-mount>}"
REPO="${2:?Usage: tryit-setup.sh <consumer-root> <repo-mount>}"

# /consumer is a bind/volume mount point and therefore always exists; refuse
# only when it is non-empty.
if [[ -n "$(ls -A "$CONSUMER" 2>/dev/null)" ]]; then
  echo "Refusing non-empty consumer root: $CONSUMER" >&2
  exit 2
fi

# Git checkouts on Windows carry CRLF shell scripts; normalize a writable
# copy of the needed script trees so bash runs them unchanged.
mkdir -p /work
cp -r "$REPO/scripts" /work/scripts
cp -r "$REPO/examples" /work/examples
find /work -type f -name '*.sh' -exec sed -i 's/\r$//' {} +
REPO=/work

bash "$REPO/scripts/acceptance/a2-alpha3/run_a2_01.sh" "$CONSUMER/instance"

app="$CONSUMER/instance/app"
python3 "$REPO/examples/minimal-quickstart/install_overlay.py" "$app"

# Serve the published runtime bytes (the import map points here).
mkdir -p "$app/public/js/browser-runtime"
cp "$app/node_modules/@surfacerelay/browser-runtime/dist/"*.js "$app/public/js/browser-runtime/"

cd "$app"
php artisan migrate --force
php "$REPO/examples/minimal-quickstart/seed_demo.php"

echo
echo "======================================================================"
echo " Quickstart consumer is ready (setup container exits now)."
echo "   URL:       http://127.0.0.1:8080/admin/login"
echo "   Login:     quickstart@example.test / quickstart-password"
echo "   Page:      http://127.0.0.1:8080/admin/quickstart-orders"
echo "   Tool:      quickstart.orders.inspect.v1 (read-only, order_id 101|102)"
echo "======================================================================"
