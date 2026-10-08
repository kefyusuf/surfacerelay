# T-910 local Filament helper candidate

This separate disposable consumer uses the current live example with the new
public Filament browser adapter. Historical T-907/T-908 clients retain their
compatible published-version glue. No registry package is republished here.

The generator reuses T-908's pinned Laravel/Filament consumer, authenticated
tenant context, response-loss fault fixture and ownership checks. It changes
the generated client to public root imports from the supplied local npm tarball,
an explicitly shared selection coordinator and opt-in execution envelopes.
It writes only `.tmp/t910-filament-demo`, marked by `.t910-owned` containing
`surfacerelay-t910-filament`. Refresh requires `--refresh-owned`; redirected
paths and unrelated existing directories fail closed.

From the repository, with a built local candidate exposing the new adapter:

For full artifact preparation, run `verify_candidate.py` only in a task-owned
POSIX container or disposable writable source copy. Set `T910_SOURCE_REVISION` to
the source/base Git revision supplied by the caller. It runs canonical validation,
browser tests/typecheck/build/conformance, creates/installs a clean tarball consumer
and prepares the mounted Filament demo. It starts no server and claims no native
acceptance. `.tmp/t910-verification.json` records working-tree provenance; the
revision field alone does not establish exact committed-source identity. This helper
leaves its candidate/consumer/demo for native inspection; remove the enclosing owned
container/copy after retaining sanitized evidence. It never publishes a package.

```sh
python scripts/acceptance/t910-filament/test_generator.py
python scripts/acceptance/t910-filament/generate.py --browser-artifact /absolute/path/to/local-candidate.tgz
cd .tmp/t910-filament-demo
composer install --no-dev --no-interaction --prefer-dist
composer validate --strict
npm install --no-audit --no-fund
npm ci --no-audit --no-fund
npm run build
php setup.php
php ../../scripts/acceptance/t907-filament/command.php filament:assets
PILOT_CONFIRMATION_TTL=60 php -S 127.0.0.1:4187 -t public public/router.php
```

The browser artifact must remain visible at the path recorded in the generated
manifest. Regenerate inside Docker when host and container paths differ. The
first npm install creates only the disposable candidate lock. Source locks and
old consumer directories are preserved. POSIX environment syntax is shown;
adapt it for PowerShell.

Run the preserved signed HTTP suites sequentially with the same receipt TTL as
the server, and the exact generated SQLite database path:

```sh
PILOT_URL=http://127.0.0.1:4187 PILOT_DATABASE="$PWD/database/acceptance.sqlite" PILOT_CONFIRMATION_TTL=60 \
  python ../../scripts/acceptance/t907-filament/filament_acceptance.py
PILOT_URL=http://127.0.0.1:4187 PILOT_DATABASE="$PWD/database/acceptance.sqlite" PILOT_CONFIRMATION_TTL=60 \
  python ../../scripts/acceptance/t908-filament/fault_acceptance.py
```

Generation tests prove destination safety and candidate wiring; they do not
qualify installed package exports, a real mounted application or native calls.
The actual candidate must build successfully. Native acceptance must use the
real Filament login/table/Approve UI, inspect nested envelope business output
and compare SQL effects. The simulated response-loss control is generated as
`fault_control.py` and recognizes only this task marker; use its `arm`,
`evidence` and `disarm` commands as documented in the T-908 recipe.

At completion, terminate only the server/stack created for this task. Verify the
resolved generated directory is within this repository and its marker matches
before removing it. Preserve unrelated containers, volumes, images and demos.
