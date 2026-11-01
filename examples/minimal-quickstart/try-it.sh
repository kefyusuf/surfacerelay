#!/usr/bin/env bash
# T-922 host-side wrapper: one command that stands up the quickstart demo.
#
#   bash examples/minimal-quickstart/try-it.sh
#
# Requires Docker. Builds the toolchain image if missing, creates one
# disposable named volume, installs a fresh registry-only consumer with the
# documented quickstart code, seeds synthetic demo data, and serves it on
# http://127.0.0.1:8080. Then follow the printed Chrome checklist.
set -Eeuo pipefail
export MSYS_NO_PATHCONV=1   # Git Bash: stop mangling /paths inside docker args

IMAGE=surfacerelay-tryit
VOLUME=surfacerelay-tryit
PORT="${1:-8080}"
REPO="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/../.." && pwd -P)"
if [[ "$REPO" != *[:\\\\]* && -d "$(cygpath -u "$REPO" 2>/dev/null || true)" ]]; then
  REPO="$(cygpath -m "$REPO" 2>/dev/null || echo "$REPO")"   # E:/... form for docker -v
fi

command -v docker >/dev/null || { echo "Docker is required." >&2; exit 2; }

if ! docker image inspect "$IMAGE" >/dev/null 2>&1; then
  echo "Building toolchain image ($IMAGE) — one-time, a few minutes..."
  docker build -q -t "$IMAGE" "$REPO/examples/minimal-quickstart/docker" >/dev/null
fi

docker volume create "$VOLUME" >/dev/null
if [[ -n "$(docker run --rm -v "$VOLUME:/consumer" "$IMAGE" find /consumer -mindepth 1 -maxdepth 1 2>/dev/null | head -1)" ]]; then
  echo "Volume $VOLUME already has a consumer. Reuse it: docker start -a surfacerelay-tryit-demo" >&2
  echo "or remove it first: docker volume rm $VOLUME" >&2
  exit 2
fi

echo "Installing a fresh registry-only consumer (first run: ~5 minutes)..."
docker run --rm --name surfacerelay-tryit-setup \
  -v "$REPO:/repo:ro" -v "$VOLUME:/consumer" \
  "$IMAGE" bash /repo/examples/minimal-quickstart/tryit-setup.sh /consumer /repo >/dev/null

docker rm -f surfacerelay-tryit-demo >/dev/null 2>&1 || true
docker run -d --name surfacerelay-tryit-demo -p "$PORT":8080 \
  -e PHP_CLI_SERVER_WORKERS=8 \
  -v "$VOLUME:/consumer" -w /consumer/instance/app \
  "$IMAGE" bash -c "php artisan optimize -n >/dev/null 2>&1; exec php artisan serve --host=0.0.0.0 --port=8080" >/dev/null

for _ in $(seq 1 30); do
  code=$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/admin/login" 2>/dev/null || true)
  [[ "$code" == 200 || "$code" == 302 ]] && break
  sleep 2
done

if [[ "$code" != 200 && "$code" != 302 ]]; then
  echo
  echo "======================================================================"
  echo " WARNING: the consumer is running, but http://127.0.0.1:$PORT is not"
  echo " reachable from this host. Docker's port forwarder"
  echo " (com.docker.backend.exe) is not binding published ports."
  echo
  echo " Tried and ruled out: not a repo issue (server responds inside the"
  echo " container; plain host processes CAN bind the same port), not a"
  echo " Windows port-exclusion range, persists across Docker and WSL"
  echo " restarts."
  echo
  echo " Known fixes, in order of likelihood:"
  echo "   1. Antivirus per-application firewall rule: allow"
  echo "      com.docker.backend.exe to listen (e.g. Kaspersky Settings ->"
  echo "      Security -> Firewall -> Application rules)."
  echo "   2. Full restart: quit Docker Desktop, run 'wsl --shutdown',"
  echo "      start Docker Desktop again, then re-run this script."
  echo "   3. Try a different port: bash $0 9090"
  echo
  echo " The consumer stays in the '$VOLUME' volume; re-running this"
  echo " script after the fix reuses it (no reinstall)."
  echo "======================================================================"
  exit 1
fi

echo
echo "======================================================================"
echo " Quickstart demo is running:  http://127.0.0.1:$PORT"
echo
echo " 1. Login (Chrome with WebMCP enabled, see below):"
echo "      http://127.0.0.1:$PORT/admin/login"
echo "      quickstart@example.test / quickstart-password"
echo " 2. Open the orders page:"
echo "      http://127.0.0.1:$PORT/admin/quickstart-orders"
echo " 3. Browser check (Chrome 150+ with WebMCP):"
echo "      DevTools console ->  window.__quickstartRegistration"
echo "      expect { registered: true, ... }"
echo " 4. Agent check (if you use chrome-devtools-mcp):"
echo "      list_webmcp_tools -> quickstart.orders.inspect.v1"
echo "      execute_webmcp_tool { toolName: 'quickstart.orders.inspect.v1',"
echo "                            input: '{\"order_id\":101}' }"
echo
echo " Stop everything:  docker rm -f surfacerelay-tryit-demo"
echo " Reset completely: docker rm -f surfacerelay-tryit-demo; docker volume rm $VOLUME"
echo "======================================================================"
