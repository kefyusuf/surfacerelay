# Disposable Filament registry consumer

This is a bounded acceptance recipe, not a maintained demo application or a
production starter. It generates `.tmp/t907-filament-demo/` from the existing
T-906 scaffold, a pinned gateway transformation and Filament templates. Exact
Composer/npm locks install the published alpha packages without a path repository.
The temporary application must be removed after verification.

The generated panel uses actual Filament list/edit resource pages, the published
Filament gateway and confirmation bridge, and published Livewire bindings/runtime.
It writes only a simulated `effects` ledger. Explicit checkbox selection is
supported; tracking all records is rejected. Selection synchronization stays
app/example glue under proposed D-075.

The real Filament login form at `/admin/login` uses the disposable fixture user
`owner@example.test` / `acceptance-password`. Successful form authentication sets
`tenant-a` only after verifying membership; missing membership logs out. These
credentials and the fixed tenant are test data, not a production login policy.

From the repository root, with PHP 8.4, Composer, Node 22 and Python 3.12:

```sh
python3 scripts/acceptance/t907-filament/test_generator.py
python3 scripts/acceptance/t907-filament/generate.py
cd .tmp/t907-filament-demo
composer install --no-dev --no-interaction --prefer-dist
composer validate --strict
npm ci --no-audit --no-fund
npm run build
php setup.php
php ../../scripts/acceptance/t907-filament/command.php filament:assets
php -S 127.0.0.1:4187 -t public public/router.php
```

In a second terminal, from the repository root:

```sh
PILOT_URL=http://127.0.0.1:4187 \
PILOT_DATABASE="$PWD/.tmp/t907-filament-demo/database/acceptance.sqlite" \
python3 scripts/acceptance/t907-filament/filament_acceptance.py
```

These are POSIX command examples; use the equivalent environment syntax on
Windows. Stop the server, verify the resolved target is the generated directory,
then remove that directory. Never remove `.tmp` as a whole. The generator refuses
an existing nonempty target; `--refresh-owned` is only for a verified marker-owned
directory and rejects redirected entries. Do not use real credentials/data.

Fixture login is `owner@example.test` / `acceptance-password`. The panel is local
only. Confirmation receipts default to five seconds for bounded HTTP testing.
For a later native run, set `PILOT_CONFIRMATION_TTL=60` on the server process;
expiry HTTP tests require the same value in their environment.
Native acceptance was completed using the owner-authorized Chrome alternative,
through official Chrome DevTools MCP discovery/invocation tools in an isolated
profile restricted to local port 4187. In-app loopback access remains an unresolved
environment limit. Passing HTTP tests alone do not prove native discovery,
Alpine synchronization or browser lifecycle. See the evidence for the native
matrix, SQL effect boundaries and the temporary CSRF-protected tenant test form.
See [executed evidence](../../../docs/reviews/t907-filament-acceptance.md).

## Chrome native WebMCP setup

The tested agent connection uses official [Chrome DevTools MCP](https://github.com/ChromeDevTools/chrome-devtools-mcp), pinned to `1.10.1`, with Chrome 150+ and a supported Node version (`^20.19.0`, `^22.12.0`, or `>=23`). This is local acceptance tooling, not a SurfaceRelay package dependency. A browser exposing `document.modelContext` is only one requirement: the agent connection must also expose `list_webmcp_tools` and `execute_webmcp_tool`.

For a manually opened Chrome profile, enable `chrome://flags/#enable-webmcp-testing` and relaunch Chrome. The isolated connection below instead enables WebMCP on its own Chrome process; it does not attach to the personal profile or inherit that profile's flags.

Add the following project-local `.codex/config.toml` section, preserving other settings. This is the tested Windows Node/Chrome launcher; adjust installation paths on another machine. On macOS/Linux, use `command = "npx"` and remove the `npx-cli.js` entry from `args`.

```toml
[mcp_servers.chrome-webmcp]
command = 'C:\Program Files\nodejs\node.exe'
args = [
  'C:\Program Files\nodejs\node_modules\npm\bin\npx-cli.js',
  '--yes', 'chrome-devtools-mcp@1.10.1',
  '--executablePath=C:\Program Files\Google\Chrome\Application\chrome.exe',
  '--isolated=true', '--headless=true',
  '--categoryExperimentalWebmcp=true', '--chromeArg=--enable-features=WebMCP',
  '--allowedUrlPattern=http://127.0.0.1:4187/*',
  '--allowedUrlPattern=http://localhost:4187/*',
  '--javascriptEvaluation=false',
  '--categoryPerformance=false', '--categoryEmulation=false',
  '--categoryNetwork=false', '--categoryMemory=false',
  '--usageStatistics=false', '--performanceCrux=false',
  '--redactNetworkHeaders=true'
]
startup_timeout_sec = 60
tool_timeout_sec = 60
```

On macOS/Linux, also remove or update the Windows `--executablePath` option. The Windows recipe invokes npm through Node to avoid shell quoting errors in paths containing spaces. Keep machine-specific configuration local (for example, exclude this file through `.git/info/exclude`). Codex loads project configuration only for trusted projects. Reload Codex after adding the connection; `codex mcp get chrome-webmcp --json` should report an enabled server, and the agent's active tools must include both native WebMCP operations.

Start the disposable demo on port 4187, sign in through `/admin/login`, and select records using the actual Filament checkboxes. Discover `pilot.filament.orders.refund.v1` on the list or `pilot.filament.orders.hold.v1` on an edit page, then invoke with `{"reason":"customer-request"}`. The result requires visible approval; clicking Approve alone has no business effect. Invoke the same tool again to complete, and compare the SQL effect ledger before/after and on replay. The response may contain core `confirmation_required` even when the browser's outer execution status is `Completed`.

The URL allowlist includes navigation, redirects and subresources; other ports and external fonts are blocked. Update it only when the intended local demo address changes. The profile is temporary and cleaned up when its browser closes. Close task-created pages and remove the generated demo and task-owned Docker resources after testing. To remove the connection, delete only this MCP section and reload Codex. See the [upstream configuration reference](https://github.com/ChromeDevTools/chrome-devtools-mcp/blob/main/docs/configuration.md) for flags; released-package `--help` is authoritative when upstream main differs.
