#!/usr/bin/env bash
# A2-08 stage 1+2: apply the strict one-shot overlay, then install page
# assets, migrate and seed. Usage: apply_overlay_and_assets.sh <instance-root> <repo-mount>
set -Eeuo pipefail

INSTANCE="${1:?Usage: apply_overlay_and_assets.sh <instance-root> <repo-mount>}"
REPO="${2:?Usage: apply_overlay_and_assets.sh <instance-root> <repo-mount>}"
app="$INSTANCE/app"

python3 "$REPO/scripts/acceptance/a2-02-read-pilot/install_overlay.py" "$app"
bash "$REPO/scripts/acceptance/a2-08-native-chrome/install_page_assets.sh" "$INSTANCE" "$REPO"
