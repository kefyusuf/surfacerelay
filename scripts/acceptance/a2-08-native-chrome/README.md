# SurfaceRelay A2-08 — real Chrome `document.modelContext` discovery/invocation proof

**State: LOCAL NATIVE PASS — DISCOVERY + INVOCATION + NEGATIVES.** Committed receipts record one full passing run on this machine: Chrome `155.0.8059.85` with the WebMCP feature, official [Chrome DevTools MCP](https://github.com/ChromeDevTools/chrome-devtools-mcp) pinned to `1.10.1` (`--categoryExperimentalWebmcp=true --chromeArg=--enable-features=WebMCP --isolated`), driving the agent surface itself (`list_webmcp_tools` / `execute_webmcp_tool`) — the same accepted pattern as T-907/T-910. No runtime, schema, fixture or release bytes change; consumer-only page wiring.

## Verified live (see `proof-result.json`, `guest-result.json`, dumps)

1. **Registration:** the app-owned page module registers the server-issued descriptor on the mounted Filament page through the B2 glue (`registered: true`, exact component id) — `registered` is only possible when `document.modelContext.registerTool` exists AND a component carries the descriptor.
2. **Discovery:** `list_webmcp_tools` returns exactly one tool, `a2.orders.inspect.v1`, with the canonical input schema and annotations (`readOnly: true`, `untrustedContent: false`, `consequential: false`) — the B2 envelope-mode description is present.
3. **Invocation:** `execute_webmcp_tool` for `order_id: 101` returns the projection-only envelope (`surfacerelay.webmcp.execution.v1`, `status: 'returned'`, `output.kind: 'value'`, `value: null` for the void server method) — Chrome may report the outer status `Completed` for every arm, exactly as the consumer guide documents. The dispatch created a server audit row (`audit rows 7 -> 8`), so the agent route reaches the **same** trusted server operation as the human route.
4. **Negatives:** a forged `forged_tenant` field and a missing required input yield `execution_failed` (unknown outcome) without disclosing errors; a foreign-tenant order keeps the envelope `returned` because the void method resolves normally — the denial is proven in server-owned page state (`inspectionStatus === 'denied'`); navigating away disposes the lease (tool disappears) and returning re-registers it; an unauthenticated visitor lands on `/admin/login` with **no WebMCP tools** (`PASS_A2_08_GUEST_NO_DISCOVERY`) — discovery is not authorization.

## Local reproduction

Prerequisites: Windows host with Chrome 150+ (`--executablePath` below), Node 23+, Docker. The consumer runs inside `sr-php84-node` (php 8.4 + composer + Node 22 + python3; built from `../../a2-02-browser-pilot` sibling: see `setup_consumer.sh` requirements). Ports: serve on 8080.

```sh
docker volume create a2-08-consumer
docker run --rm --name a2-08-setup -v "<repo>:/repo:ro" -v a2-08-consumer:/consumer \
  sr-php84-node bash /repo/scripts/acceptance/a2-08-native-chrome/setup_consumer.sh /consumer /repo
# when setup reaches "Server running", stop it and serve with port mapping:
docker stop a2-08-setup
docker run --rm -d --name a2-08-serve -p 8080:8080 -e PHP_CLI_SERVER_WORKERS=8 \
  -v a2-08-consumer:/consumer -w /consumer/instance/app \
  sr-php84-node bash -c "php artisan optimize -n; exec php artisan serve --host=0.0.0.0 --port=8080"

cd scripts/acceptance/a2-08-native-chrome
node run_native_proof.mjs proof   # discovery + invocation + negatives + receipts
node run_native_proof.mjs guest   # unauthenticated discovery negative
```

The harness kills its whole Chrome process tree on exit; each run is a fresh isolated profile. Login uses the seeded member (`a2.member@example.test` / `a2-acceptance-password`, synthetic data only).

## Consumer-only changes

- `A2OrdersProvider.php` (read-pilot overlay, additive): a `BODY_END` render hook injects one import map (published runtime bytes) plus the registration module on panel pages. It injects delivery wiring only; the module self-guards.
- `a2-surfacerelay-page.mjs`: self-guarding registration module — registers only when `document.modelContext.registerTool` exists AND a mounted component carries the locked `surfacerelayDescriptor`; disposes on `livewire:navigating`, re-registers on `livewire:navigated`; never throws into the host page.
- `install_page_assets.sh` / `apply_overlay_and_assets.sh` / `setup_consumer.sh`: stage the registry-installed consumer, copy the glue + page module + the **published** `dist` tree (relative module files, hashed in `page-assets-sha256.txt`), migrate, seed.
- `A2PageWiringTest.php` + `check_wiring_junit.py` + `.github/workflows/a2-08-page-wiring-consumer.yml`: hosted, Chrome-free proof that the page serves the wiring and embeds the descriptor (`PASS_A2_08_PAGE_WIRING_ONLY`), with A2-02a/B1/B2 regressions.

## Boundaries

No write path, new driver, runtime change, schema change or release. The live proof covers the read-only consumer only; consequential/write actions stay A2-04…A2-07 territory. A local run is machine-specific evidence; the hosted workflow proves the wiring contract Chrome-free.
