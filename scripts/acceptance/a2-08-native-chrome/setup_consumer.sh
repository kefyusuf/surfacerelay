#!/usr/bin/env bash
# A2-08: create the disposable registry consumer INSIDE a container, apply the
# overlay, install the page assets (published bytes), migrate and seed.
# Runs inside the sr-php84-node image with /repo mounted read-only.
set -Eeuo pipefail

CONSUMER_ROOT="${1:?Usage: setup_consumer.sh /absolute/consumer/root}"
REPO="${2:?Usage: setup_consumer.sh <consumer-root> <repo-mount>}"

# /consumer is a bind-mount point and therefore always exists; refuse only
# when it is non-empty.
if [[ -n "$(ls -A "$CONSUMER_ROOT" 2>/dev/null)" ]]; then
  echo "Refusing non-empty consumer root: $CONSUMER_ROOT" >&2
  exit 2
fi

# Git checkouts on Windows carry CRLF shell scripts; normalize a writable
# copy of the acceptance tree so bash runs it unchanged.
mkdir -p /work
cp -r "$REPO/scripts" /work/scripts
find /work/scripts -type f -name '*.sh' -exec sed -i 's/\r$//' {} +
REPO=/work

bash "$REPO/scripts/acceptance/a2-alpha3/run_a2_01.sh" "$CONSUMER_ROOT/instance"
bash "$REPO/scripts/acceptance/a2-08-native-chrome/apply_overlay_and_assets.sh" "$CONSUMER_ROOT/instance" "$REPO"

echo "A2-08 consumer setup complete; starting php artisan serve on 0.0.0.0:8080"
cd "$CONSUMER_ROOT/instance/app"
exec php artisan serve --host=0.0.0.0 --port=8080
