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
Native Codex in-app acceptance remains a separate gate; a passing HTTP job does
not prove native tool discovery, Alpine synchronization or browser lifecycle.
See [executed evidence](../../../docs/reviews/t907-filament-acceptance.md).
